/// Production hosting — Admin app always talks to cloud (not cafe LAN).
const String kDefaultServerUrl = 'https://signature.softwaresolutions.pk';

const String kDefaultServerHost = 'signature.softwaresolutions.pk';

/// Prefer cloud; keep a couple of fallbacks if HTTPS redirects / DNS odd.
List<String> kServerUrlCandidates({String? saved}) {
  final out = <String>[];
  void add(String? url) {
    final u = (url ?? '').trim().replaceAll(RegExp(r'/+$'), '');
    if (u.isEmpty || out.contains(u)) return;
    // Never auto-pick private cafe LAN for this admin app.
    if (_isPrivateLanUrl(u)) return;
    out.add(u);
  }

  add(saved);
  add(kDefaultServerUrl);
  add('https://$kDefaultServerHost');
  add('http://$kDefaultServerHost');

  return out;
}

bool _isPrivateLanUrl(String url) {
  try {
    final host = Uri.parse(url).host.toLowerCase();
    if (host == 'localhost' || host == '127.0.0.1' || host == '::1') return true;
    if (host.endsWith('.local') || host.endsWith('.test') || host.endsWith('.restro')) return true;
    final parts = host.split('.');
    if (parts.length == 4 && parts.every((p) => int.tryParse(p) != null)) {
      final a = int.parse(parts[0]);
      final b = int.parse(parts[1]);
      if (a == 10) return true;
      if (a == 192 && b == 168) return true;
      if (a == 172 && b >= 16 && b <= 31) return true;
    }
  } catch (_) {}
  return false;
}

bool isPrivateLanUrl(String url) => _isPrivateLanUrl(url);
