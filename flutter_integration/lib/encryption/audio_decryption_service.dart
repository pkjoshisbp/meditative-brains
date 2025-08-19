import 'dart:convert';
import 'dart:typed_data';
import 'package:crypto/crypto.dart';
import 'package:pointycastle/api.dart';
import 'package:pointycastle/block/aes.dart';
import 'package:pointycastle/block/modes/cbc.dart';
import 'package:pointycastle/paddings/pkcs7.dart';

/// Service for decrypting background music files encrypted by Laravel backend.
/// Uses AES-256-CBC with the same key derivation as AudioSecurityService.
class AudioDecryptionService {
  late Uint8List _encryptionKey;
  
  /// Initialize with the base64-encoded encryption key from the backend
  void setEncryptionKey(String keyBase64) {
    _encryptionKey = base64Decode(keyBase64);
  }

  /// Decrypt encrypted audio content.
  /// Format: [16-byte IV][encrypted content]
  Uint8List decryptAudio(Uint8List encryptedData) {
    if (_encryptionKey.isEmpty) {
      throw Exception('Encryption key not set. Call setEncryptionKey() first.');
    }

    if (encryptedData.length < 16) {
      throw Exception('Invalid encrypted data: too short for IV');
    }

    // Extract IV (first 16 bytes) and encrypted content
    final iv = encryptedData.sublist(0, 16);
    final encrypted = encryptedData.sublist(16);

    // Set up AES-256-CBC cipher
    final cipher = CBCBlockCipher(AESEngine());
    final params = PaddedBlockCipherParameters<ParametersWithIV, Null>(
      ParametersWithIV(KeyParameter(_encryptionKey), iv),
      null,
    );
    final paddedCipher = PaddedBlockCipher('AES/CBC/PKCS7');
    paddedCipher.init(false, params); // false = decrypt mode

    try {
      final decrypted = paddedCipher.process(encrypted);
      return decrypted;
    } catch (e) {
      throw Exception('Failed to decrypt audio: $e');
    }
  }

  /// Convenience method to decrypt and return as Stream for audio playback
  Stream<List<int>> decryptAudioStream(Uint8List encryptedData) async* {
    final decrypted = decryptAudio(encryptedData);
    // Yield in chunks for streaming
    const chunkSize = 8192;
    for (int i = 0; i < decrypted.length; i += chunkSize) {
      final end = (i + chunkSize < decrypted.length) ? i + chunkSize : decrypted.length;
      yield decrypted.sublist(i, end);
    }
  }
}
