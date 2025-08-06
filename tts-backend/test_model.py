from TTS.api import TTS
import torch

# Model paths
MODEL_PATH = "./workspace/my-vits-checkpoints/vits_test-April-15-2025_02+18AM-0000000/best_model_21.pth"
CONFIG_PATH = "./workspace/tts-dataset/training_config_xtts.json"

def test_model(text, output_file, noise_scale=0.667, length_scale=1.0):
    try:
        # Initialize TTS with detailed settings
        tts = TTS(
            model_path=MODEL_PATH,
            config_path=CONFIG_PATH,
        ).to("cpu")

        # Generate speech with controllable parameters
        wav = tts.tts(
            text=text,
            noise_scale=noise_scale,      # Controls variation in voice (0.667 is a good default)
            length_scale=length_scale,    # Controls speech speed (1.0 is normal speed)
        )

        # Save the audio
        tts.synthesizer.save_wav(wav, output_file)
        print(f"Audio saved to {output_file}")
        
    except Exception as e:
        print(f"Error generating speech: {str(e)}")

if __name__ == "__main__":
    # Test text
    test_text = "welcome to the voice synthesis system."
    
    # Try different parameter combinations
    test_model(test_text, "output_normal.wav")
    test_model(test_text, "output_clear.wav", noise_scale=0.5)
    test_model(test_text, "output_slow.wav", length_scale=1.2)
