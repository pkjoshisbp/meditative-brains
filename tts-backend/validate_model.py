import torch
import librosa
import numpy as np
import matplotlib.pyplot as plt
from TTS.api import TTS
import soundfile as sf
import os

def analyze_audio(audio_data, sr, title, output_dir):
    """Analyze generated audio and create visualizations"""
    # Create mel spectrogram
    mel_spect = librosa.feature.melspectrogram(y=audio_data, sr=sr)
    mel_spect_db = librosa.power_to_db(mel_spect, ref=np.max)
    
    plt.figure(figsize=(10, 4))
    librosa.display.specshow(mel_spect_db, sr=sr, x_axis='time', y_axis='mel')
    plt.colorbar(format='%+2.0f dB')
    plt.title(f'Mel Spectrogram - {title}')
    plt.tight_layout()
    plt.savefig(f'{output_dir}/mel_spect_{title}.png')
    plt.close()
    
    # Print audio statistics
    print(f"\nAudio Analysis - {title}")
    print(f"Max amplitude: {np.abs(audio_data).max():.3f}")
    print(f"Mean amplitude: {np.abs(audio_data).mean():.3f}")
    print(f"Zero values: {(np.abs(audio_data) < 1e-6).sum() / len(audio_data):.2%}")

def validate_model(model_path, config_path, output_dir):
    """Validate model by generating and analyzing audio"""
    os.makedirs(output_dir, exist_ok=True)
    
    # Load model
    tts = TTS(
        model_path=model_path,
        config_path=config_path
    ).to("cpu")
    
    # Test text
    test_text = "आप अपना चेहरा हमेशा प्रकाश की ओर रखते हैं।"
    
    # Generate with different parameters
    params = [
        {"noise_scale": 0.1, "length_scale": 1.0},
        {"noise_scale": 0.3, "length_scale": 1.0},
        {"noise_scale": 0.667, "length_scale": 1.0},
    ]
    
    for i, p in enumerate(params):
        try:
            # Generate audio
            wav = tts.tts(
                text=test_text,
                **p
            )
            wav = np.array(wav)
            
            # Save audio
            output_file = f"{output_dir}/test_{i}.wav"
            sf.write(output_file, wav, 16000)
            
            # Analyze generated audio
            analyze_audio(wav, 16000, f"test_{i}", output_dir)
            
            print(f"\nGenerated {output_file}")
            print(f"Parameters: {p}")
            
        except Exception as e:
            print(f"Error with parameters {p}: {str(e)}")

if __name__ == "__main__":
    MODEL_PATH = "./workspace/my-vits-checkpoints/hindi_vits_run-April-14-2025_04+55PM-0000000/best_model_56.pth"
    CONFIG_PATH = "./workspace/tts-dataset/training_config_xtts.json"
    OUTPUT_DIR = "./validation_outputs"
    
    validate_model(MODEL_PATH, CONFIG_PATH, OUTPUT_DIR)
