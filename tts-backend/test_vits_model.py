import torch
from TTS.api import TTS
import soundfile as sf
import numpy as np

MODEL_PATH = "./workspace/my-vits-checkpoints/hindi_vits_run-April-14-2025_04+55PM-0000000/best_model_56.pth"
CONFIG_PATH = "./workspace/tts-dataset/training_config_xtts.json"

def test_model(text, output_prefix, 
               noise_scales=[0.1, 0.3, 0.5, 0.667],
               length_scales=[0.8, 1.0, 1.2],
               cleanup_audio=True):
    """Test VITS model with different parameters and audio processing"""
    
    tts = TTS(model_path=MODEL_PATH,
              config_path=CONFIG_PATH).to("cpu")
    
    results = []
    for ns in noise_scales:
        for ls in length_scales:
            output_file = f"test_outputs/{output_prefix}_ns{ns}_ls{ls}.wav"
            try:
                # Generate audio
                wav = tts.tts(text=text,
                             noise_scale=ns,
                             length_scale=ls)
                
                if cleanup_audio:
                    # Apply basic audio cleanup
                    wav = np.array(wav)
                    # Normalize
                    wav = wav / np.abs(wav).max()
                    # Simple noise reduction
                    wav[np.abs(wav) < 0.05] = 0
                
                sf.write(output_file, wav, 16000)
                results.append({
                    'file': output_file,
                    'noise_scale': ns,
                    'length_scale': ls
                })
                print(f"✓ Generated: {output_file}")
                
            except Exception as e:
                print(f"Error with ns={ns}, ls={ls}: {str(e)}")
    
    return results

if __name__ == "__main__":
    test_text = "आप अपना चेहरा हमेशा प्रकाश की ओर रखते हैं।"
    
    import os
    os.makedirs("test_outputs", exist_ok=True)
    
    results = test_model(test_text, "test1")
    print("\nGeneration complete. Please check test_outputs directory.")
