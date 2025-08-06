from TTS.api import TTS
import os

MODEL_PATH = "./workspace/my-vits-checkpoints/hindi_vits_run-April-14-2025_04+30PM-0000000/best_model_55.pth"
CONFIG_PATH = "./workspace/tts-dataset/training_config_xtts.json"

def test_model(text, output_prefix, noise_scales=[0.1, 0.3, 0.667], length_scales=[0.8, 1.0, 1.2]):
    """Test the model with different parameter combinations"""
    tts = TTS(
        model_path=MODEL_PATH,
        config_path=CONFIG_PATH
    ).to("cpu")

    os.makedirs("test_outputs", exist_ok=True)

    # Try different combinations
    for noise_scale in noise_scales:
        for length_scale in length_scales:
            output_file = f"test_outputs/{output_prefix}_noise{noise_scale}_len{length_scale}.wav"
            try:
                wav = tts.tts(
                    text=text,
                    noise_scale=noise_scale,
                    length_scale=length_scale
                )
                tts.synthesizer.save_wav(wav, output_file)
                print(f"✓ Generated: {output_file}")
            except Exception as e:
                print(f"Error generating {output_file}: {str(e)}")

if __name__ == "__main__":
    test_text = "आप अपना चेहरा हमेशा प्रकाश की ओर रखते हैं।"
    test_model(test_text, "test1")
