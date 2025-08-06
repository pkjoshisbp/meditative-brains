import os
import argparse
from TTS.tts.models.glow_tts import GlowTTS
from TTS.utils.audio import AudioProcessor
from TTS.tts.utils.text.tokenizer import TTSTokenizer
from TTS.tts.configs.glow_tts_config import GlowTTSConfig
import torch

def main():
    # Parse command line arguments
    parser = argparse.ArgumentParser(description='Run GlowTTS inference')
    parser.add_argument('--text', required=True, help='Text to synthesize')
    parser.add_argument('--model_path', default="/var/www/clients/client1/web51/web/run-April-15-2025_02+50PM-0000000/best_model_13.pth",
                      help='Path to model checkpoint')
    parser.add_argument('--config_path', default="./workspace/tts-dataset/training_config_xtts.json",
                      help='Path to config file')
    parser.add_argument('--out_path', default="output.wav",
                      help='Output path for generated audio')
    args = parser.parse_args()

    # Load checkpoint and config
    if not os.path.isfile(args.model_path):
        raise RuntimeError(f"Model file not found: {args.model_path}")
    checkpoint = torch.load(args.model_path)
    
    # Initialize config properly
    config = GlowTTSConfig()
    if os.path.isfile(args.config_path):
        config.load_json(args.config_path)
    
    # Update config with checkpoint values if available
    if "config" in checkpoint:
        checkpoint_config = checkpoint["config"]
        if isinstance(checkpoint_config, dict):
            for key, value in checkpoint_config.items():
                setattr(config, key, value)

    # Initialize model components
    ap = AudioProcessor.init_from_config(config)
    tokenizer, config = TTSTokenizer.init_from_config(config)
    model = GlowTTS.init_from_config(config)
    model.load_state_dict(checkpoint["model"])
    model.eval()

    # Generate speech
    with torch.no_grad():
        inputs = torch.LongTensor(tokenizer.text_to_ids(args.text)).unsqueeze(0)
        outputs = model.inference(inputs)
        wav = outputs[0] if isinstance(outputs, tuple) else outputs
        
        # Create output directory if needed
        os.makedirs(os.path.dirname(os.path.abspath(args.out_path)), exist_ok=True)
        ap.save_wav(wav, args.out_path)
        print(f"Generated audio saved to {args.out_path}")

if __name__ == "__main__":
    main()
