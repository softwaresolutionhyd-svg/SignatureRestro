/// Cafe PC ke possible LAN addresses — Softwaresolution aksar :80, Signature :8080.
/// Settings → LAN IP se `/api/server-config` sahi URL return karta hai.
const String kDefaultServerUrl = 'http://192.168.1.105:8080';

const String kDefaultServerHost = '192.168.1.105';

/// Discovery order: pehle common Signature ports, phir HTTPS / purani IPs.
List<String> kServerUrlCandidates({String? saved}) {
  final out = <String>[];
  void add(String? url) {
    final u = (url ?? '').trim().replaceAll(RegExp(r'/+$'), '');
    if (u.isEmpty || out.contains(u)) return;
    out.add(u);
  }

  add(saved);
  add(kDefaultServerUrl);
  add('http://$kDefaultServerHost:8080');
  add('http://$kDefaultServerHost');
  add('https://$kDefaultServerHost');
  add('https://$kDefaultServerHost:8080');
  add('http://192.168.3.50:8080');
  add('http://192.168.3.50');
  add('https://192.168.3.50');

  return out;
}
