import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../config/server_config.dart';
import '../providers/app_state.dart';
import '../services/api_client.dart';
import '../services/order_notifications.dart';
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
      var url = session.baseUrl;
      // Purani LAN save clear — ye app online hosting use karti hai.
      if (url.isEmpty || isPrivateLanUrl(url)) {
        url = kDefaultServerUrl;
        await session.saveBaseUrl(url);
      }
      if (mounted) {
        _baseUrlCtrl.text = url;
      }
      await _discoverServerUrl(preferSaved: url);
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
              .timeout(const Duration(seconds: 8));
          if (res.statusCode != 200) continue;
          final data = jsonDecode(res.body);
          if (data is! Map<String, dynamic>) continue;
          final hasSig = data.containsKey('order_taker_app') || data.containsKey('server_url');
          if (!hasSig) continue;

          // Cloud pe raho — server_config ka LAN server_url ignore.
          if (mounted) {
            setState(() => _baseUrlCtrl.text = tryUrl);
          }
          return;
        } catch (_) {}
      }
      if (mounted && _baseUrlCtrl.text.trim().isEmpty) {
        setState(() => _baseUrlCtrl.text = kDefaultServerUrl);
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
        'email': login,
        'password': _passwordCtrl.text,
        'app': 'admin',
      });

      final user = Map<String, dynamic>.from(res['user'] as Map);
      await session.login(
        token: res['token']?.toString() ?? '',
        name: user['name']?.toString() ?? '',
        email: user['email']?.toString() ?? login,
        role: user['role']?.toString() ?? '',
      );

      await OrderNotificationService.instance.start(session.client);

      if (!mounted) return;
      await context.read<AppState>().refreshDashboard();
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
    final cs = Theme.of(context).colorScheme;
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
                  Image.asset('assets/stair_icon.png', width: 88, height: 88),
                  const SizedBox(height: 12),
                  Text(
                    'Stair',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Online admin — signature.softwaresolutions.pk',
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
                      hintText: 'admin',
                      prefixIcon: Icon(Icons.person_outline),
                    ),
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
                    Text(_error!, style: TextStyle(color: cs.error)),
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
