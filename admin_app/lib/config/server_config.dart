/// Cafe PC LAN — pehle :80 try (current Laragon), phir :8080.
const String kDefaultServerUrl = 'http://192.168.1.105';

const String kDefaultServerHost = '192.168.1.105';

List<String> kServerUrlCandidates({String? saved}) {
  final out = <String>[];
  void add(String? url) {
    final u = (url ?? '').trim().replaceAll(RegExp(r'/+$'), '');
    if (u.isEmpty || out.contains(u)) return;
    out.add(u);
  }

  add(saved);
  add(kDefaultServerUrl);
  add('http://$kDefaultServerHost');
  add('http://$kDefaultServerHost:8080');
  add('https://$kDefaultServerHost');
  add('https://$kDefaultServerHost:8080');
  add('http://192.168.3.50');
  add('http://192.168.3.50:8080');
  add('https://192.168.3.50');

  return out;
}
