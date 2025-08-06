from TTS.api import TTS
import torch
import os

MODEL_PATH = "./workspace/my-vits-checkpoints/hindi_vits_run-April-14-2025_04+55PM-0000000/best_model_56.pth"
CONFIG_PATH = "./workspace/tts-dataset/training_config_xtts.json"

def test_inference(text, output_file, noise_scale=0.667, length_scale=1.0):
    try:
        # Initialize TTS
        tts = TTS(
            model_path=MODEL_PATH,
            config_path=CONFIG_PATH,
        ).to("cpu")

        # Generate speech with different parameters
        wav = tts.tts(
            text=text,
            noise_scale=noise_scale,      # Controls voice variation (0.1 to 1.0)
            length_scale=length_scale,    # Controls speed (0.8 to 1.2)
        )

        # Save the audio
        tts.synthesizer.save_wav(wav, output_file)
        print(f"✓ Generated: {output_file}")
        
    except Exception as e:
        print(f"Error during inference: {str(e)}")

if __name__ == "__main__":
    os.makedirs("test_outputs", exist_ok=True)
    
    test_text = "आप अपना चेहरा हमेशा प्रकाश की ओर रखते हैं।"
    
    # Test different parameter combinations
    test_inference(test_text, "test_outputs/normal.wav")
    test_inference(test_text, "test_outputs/clear.wav", noise_scale=0.3)
    test_inference(test_text, "test_outputs/slow.wav", length_scale=1.2)
    test_inference(test_text, "test_outputs/fast.wav", length_scale=0.8)
    test_inference(test_text, "test_outputs/variation.wav", noise_scale=0.9)
