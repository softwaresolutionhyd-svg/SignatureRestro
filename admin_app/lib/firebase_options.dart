// File generated from Firebase project stair-66282 (google-services.json).
// ignore_for_file: lines_longer_than_80_chars, avoid_classes_with_only_static_members

import 'package:firebase_core/firebase_core.dart' show FirebaseOptions;
import 'package:flutter/foundation.dart' show defaultTargetPlatform, kIsWeb, TargetPlatform;

/// Default [FirebaseOptions] for Stair admin (project: stair-66282).
class DefaultFirebaseOptions {
  static bool get isConfigured {
    final o = currentPlatform;
    return o.apiKey.isNotEmpty &&
        !o.apiKey.startsWith('REPLACE_') &&
        o.projectId.isNotEmpty &&
        !o.projectId.startsWith('REPLACE_') &&
        o.appId.isNotEmpty &&
        !o.appId.startsWith('REPLACE_');
  }

  static FirebaseOptions get currentPlatform {
    if (kIsWeb) {
      return web;
    }
    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return android;
      case TargetPlatform.iOS:
        return ios;
      default:
        return android;
    }
  }

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'AIzaSyDXzRsggVThwkJ9wyoY-BrtampnkqGuouA',
    appId: '1:80736051:android:64930837fb5758736fd846',
    messagingSenderId: '80736051',
    projectId: 'stair-66282',
    storageBucket: 'stair-66282.firebasestorage.app',
  );

  // iOS not registered yet — mirrors Android project ids for compile safety.
  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'AIzaSyDXzRsggVThwkJ9wyoY-BrtampnkqGuouA',
    appId: '1:80736051:android:64930837fb5758736fd846',
    messagingSenderId: '80736051',
    projectId: 'stair-66282',
    storageBucket: 'stair-66282.firebasestorage.app',
    iosBundleId: 'com.softwaresolution.adminApp',
  );

  static const FirebaseOptions web = FirebaseOptions(
    apiKey: 'AIzaSyDXzRsggVThwkJ9wyoY-BrtampnkqGuouA',
    appId: '1:80736051:android:64930837fb5758736fd846',
    messagingSenderId: '80736051',
    projectId: 'stair-66282',
    storageBucket: 'stair-66282.firebasestorage.app',
  );
}
