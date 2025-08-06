import torch
from TTS.tts.configs.vits_config import VitsConfig
from TTS.tts.models.vits import Vits
import matplotlib.pyplot as plt
import librosa
import soundfile as sf
import os

def analyze_audio(audio_path):
    """Analyze input audio files"""
    y, sr = librosa.load(audio_path, sr=16000)
    
    # Plot waveform
    plt.figure(figsize=(10, 4))
    plt.subplot(2, 1, 1)
    plt.plot(y)
    plt.title('Waveform')
    
    # Plot mel spectrogram
    mel_spect = librosa.feature.melspectrogram(y=y, sr=sr, n_mels=80)
    mel_spect_db = librosa.power_to_db(mel_spect, ref=np.max)
    plt.subplot(2, 1, 2)
    librosa.display.specshow(mel_spect_db, sr=sr, x_axis='time', y_axis='mel')
    plt.colorbar(format='%+2.0f dB')
    plt.title('Mel Spectrogram')
    
    plt.tight_layout()
    plt.savefig('audio_analysis.png')
    plt.close()
    
    print(f"Audio stats:")
    print(f"Duration: {len(y)/sr:.2f}s")
    print(f"Range: {y.min():.3f} to {y.max():.3f}")
    print(f"Mean: {y.mean():.3f}")
    print(f"Std: {y.std():.3f}")

def main():
    # Check first audio file
    audio_path = "workspace/tts-dataset/0001.wav"
    if os.path.exists(audio_path):
        analyze_audio(audio_path)
    else:
        print(f"Audio file not found: {audio_path}")

if __name__ == "__main__":
    main()
