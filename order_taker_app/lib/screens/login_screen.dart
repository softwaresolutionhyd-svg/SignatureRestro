import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';

import '../config/server_config.dart';
import '../providers/app_state.dart';
import '../services/api_client.dart';
import '../services/session.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _baseUrlCtrl = TextEditingController(text: kDefaultServerUrl);
  final _loginCtrl = TextEditingController();
  final _passwordCtrl = TextEditingController();
  bool _busy = false;
  bool _discovering = false;
  String? _error;
  bool _obscure = true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final session = context.read<Session>();
      if (session.baseUrl.isNotEmpty) {
        _baseUrlCtrl.text = session.baseUrl;
      }
      await _discoverServerUrl(preferSaved: session.baseUrl);
    });
  }

  Future<void> _discoverServerUrl({String? preferSaved}) async {
    if (!mounted) return;
    setState(() => _discovering = true);
    final client = createLanHttpClient();
    try {
      for (final tryUrl in kServerUrlCandidates(saved: preferSaved)) {
        try {
          final uri = Uri.parse('$tryUrl/api/server-config');
          final res = await client
              .get(uri, headers: {'Accept': 'application/json'})
              .timeout(const Duration(seconds: 3));
          if (res.statusCode != 200) continue;
          final data = jsonDecode(res.body);
          if (data is! Map<String, dynamic>) continue;
          // Confirm it's Signature (not Softwaresolution HTML/JSON)
          final hasOt = data.containsKey('order_taker_app') || data.containsKey('server_url');
          if (!hasOt) continue;
          final url = (data['server_url'] as String?)?.trim();
          if (url != null && url.isNotEmpty && mounted) {
            setState(() => _baseUrlCtrl.text = url.replaceAll(RegExp(r'/+$'), ''));
            return;
          }
          if (mounted) {
            setState(() => _baseUrlCtrl.text = tryUrl);
          }
          return;
        } catch (_) {
          // try next
        }
      }
    } finally {
      client.close();
      if (mounted) setState(() => _discovering = false);
    }
  }

  @override
  void dispose() {
    _baseUrlCtrl.dispose();
    _loginCtrl.dispose();
    _passwordCtrl.dispose();
    super.dispose();
  }

  Future<void> _login() async {
    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final session = context.read<Session>();
      final baseUrl = _baseUrlCtrl.text.trim().replaceAll(RegExp(r'/+$'), '');
      if (baseUrl.isEmpty) {
        throw ApiException('Server URL likhein (e.g. http://192.168.1.105:8080)');
      }
      await session.saveBaseUrl(baseUrl);

      final login = _loginCtrl.text.trim();
      if (login.isEmpty) {
        throw ApiException('Username likhein');
      }

      final client = ApiClient(baseUrl: baseUrl, token: '');
      final res = await client.post('/api/login', {
        'login': login,
        'email': login, // backward compatible
        'password': _passwordCtrl.text,
      });

      final user = Map<String, dynamic>.from(res['user'] as Map);
      await session.login(
        token: res['token']?.toString() ?? '',
        name: user['name']?.toString() ?? '',
        email: user['email']?.toString() ?? login,
      );

      if (!mounted) return;
      await context.read<AppState>().refreshAll();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Icon(Icons.restaurant_menu, size: 56, color: Theme.of(context).colorScheme.primary),
                  const SizedBox(height: 12),
                  Text(
                    'Order Taker',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Same WiFi — Server URL mein Signature IP:port likhein.',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: Colors.grey.shade700),
                  ),
                  const SizedBox(height: 28),
                  TextField(
                    controller: _baseUrlCtrl,
                    decoration: InputDecoration(
                      labelText: 'Server URL',
                      hintText: kDefaultServerUrl,
                      prefixIcon: const Icon(Icons.dns_outlined),
                      suffixIcon: _discovering
                          ? const Padding(
                              padding: EdgeInsets.all(12),
                              child: SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(strokeWidth: 2),
                              ),
                            )
                          : IconButton(
                              tooltip: 'Auto-find server',
                              icon: const Icon(Icons.refresh),
                              onPressed: _busy ? null : () => _discoverServerUrl(preferSaved: _baseUrlCtrl.text),
                            ),
                    ),
                    keyboardType: TextInputType.url,
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _loginCtrl,
                    decoration: const InputDecoration(
                      labelText: 'Username',
                      hintText: 'ordertaker / admin',
                      prefixIcon: Icon(Icons.person_outline),
                    ),
                    keyboardType: TextInputType.text,
                    textInputAction: TextInputAction.next,
                    autocorrect: false,
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _passwordCtrl,
                    decoration: InputDecoration(
                      labelText: 'Password',
                      prefixIcon: const Icon(Icons.lock_outline),
                      suffixIcon: IconButton(
                        icon: Icon(_obscure ? Icons.visibility : Icons.visibility_off),
                        onPressed: () => setState(() => _obscure = !_obscure),
                      ),
                    ),
                    obscureText: _obscure,
                    onSubmitted: (_) => _busy ? null : _login(),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                  ],
                  const SizedBox(height: 20),
                  FilledButton.icon(
                    onPressed: _busy ? null : _login,
                    icon: _busy
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                          )
                        : const Icon(Icons.login),
                    label: Text(_busy ? 'Login...' : 'Login'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
