import 'package:flutter/material.dart';
import 'package:audioplayers/audioplayers.dart'; // or your current audio plugin

class AacAudioTestWidget extends StatefulWidget {
  @override
  _AacAudioTestWidgetState createState() => _AacAudioTestWidgetState();
}

class _AacAudioTestWidgetState extends State<AacAudioTestWidget> {
  final AudioPlayer _audioPlayer = AudioPlayer();
  bool _isPlaying = false;
  
  // Test URLs - replace with your actual AAC audio URLs
  final String _aacTestUrl = 'https://motivation.mywebsolutions.co.in:3000/audio-cache/en-US/test-category/en-US-AvaMultilingualNeural/test-audio.aac';
  final String _mp3FallbackUrl = 'https://motivation.mywebsolutions.co.in:3000/audio-cache/en-US/test-category/en-US-AvaMultilingualNeural/test-audio.mp3';

  @override
  void initState() {
    super.initState();
    _testAacSupport();
  }

  Future<void> _testAacSupport() async {
    print('🧪 Testing AAC support...');
    
    try {
      // Test 1: Try to load AAC file
      await _audioPlayer.setSourceUrl(_aacTestUrl);
      print('✅ AAC file loaded successfully!');
      
      // Test 2: Try to play briefly
      await _audioPlayer.play(_aacTestUrl);
      await Future.delayed(Duration(milliseconds: 500));
      await _audioPlayer.stop();
      
      print('✅ AAC playback successful!');
      _showResult('AAC Support: ✅ WORKING', Colors.green);
      
    } catch (e) {
      print('❌ AAC playback failed: $e');
      print('🔄 Falling back to MP3...');
      
      try {
        await _audioPlayer.setSourceUrl(_mp3FallbackUrl);
        print('✅ MP3 fallback successful');
        _showResult('AAC not supported, using MP3 fallback', Colors.orange);
      } catch (e2) {
        print('❌ Both AAC and MP3 failed: $e2');
        _showResult('Audio playback failed', Colors.red);
      }
    }
  }

  void _showResult(String message, Color color) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: color,
        duration: Duration(seconds: 4),
      ),
    );
  }

  Future<void> _playAacAudio() async {
    try {
      if (_isPlaying) {
        await _audioPlayer.stop();
        setState(() => _isPlaying = false);
      } else {
        await _audioPlayer.play(_aacTestUrl);
        setState(() => _isPlaying = true);
      }
    } catch (e) {
      print('Playback error: $e');
      // Fallback to MP3
      await _audioPlayer.play(_mp3FallbackUrl);
      setState(() => _isPlaying = true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('AAC Audio Test - Flutter')),
      body: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              _isPlaying ? Icons.pause_circle : Icons.play_circle,
              size: 80,
              color: Colors.blue,
            ),
            SizedBox(height: 20),
            ElevatedButton(
              onPressed: _playAacAudio,
              child: Text(_isPlaying ? 'Stop AAC Audio' : 'Play AAC Audio'),
            ),
            SizedBox(height: 20),
            Container(
              padding: EdgeInsets.all(16),
              margin: EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.grey[100],
                borderRadius: BorderRadius.circular(8),
              ),
              child: Column(
                children: [
                  Text(
                    'AAC Audio Format Details',
                    style: TextStyle(fontWeight: FontWeight.bold),
                  ),
                  SizedBox(height: 8),
                  Text('Format: AAC LC (Low Complexity)'),
                  Text('Bitrate: 192kbps'),
                  Text('Sample Rate: 48kHz'),
                  Text('Channels: Mono'),
                  Text('Compatibility: iOS, Android, Web'),
                ],
              ),
            ),
            SizedBox(height: 20),
            Text(
              'This test will automatically detect AAC support\nand work seamlessly on iOS, macOS, and Android.',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey[600]),
            ),
          ],
        ),
      ),
    );
  }

  @override
  void dispose() {
    _audioPlayer.dispose();
    super.dispose();
  }
}
