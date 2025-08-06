import torch
import numpy as np
import soundfile as sf
from TTS.tts.configs.vits_config import VitsConfig
from TTS.tts.models.vits import Vits
from TTS.tts.utils.text.tokenizer import TTSTokenizer
import matplotlib.pyplot as plt

def load_model_carefully(model, checkpoint):
    """Load state dict while handling missing/unexpected keys"""
    state_dict = checkpoint["model"]
    
    # Get dimensions from checkpoint
    emb_shape = state_dict["text_encoder.emb.weight"].shape
    dur_shape = state_dict["duration_predictor.proj.weight"].shape
    
    # Update config with proper dimensions
    model.config.model_args.hidden_channels = emb_shape[1]
    model.config.model_args.duration_predictor_channels = dur_shape[0]
    
    # Initialize tokenizer
    model.config.characters.pad_id = 0
    model.config.characters.eos_id = 1
    model.config.characters.bos_id = 2
    model.tokenizer = TTSTokenizer(model.config.characters)
    
    # Reinitialize model with updated config
    model = Vits(model.config)
    model.eval()
    
    # Fix dimensions for problematic layers
    def fix_dimension(tensor, target_shape):
        if tensor.shape != target_shape:
            if len(tensor.shape) == len(target_shape):
                # Pad or trim to match size
                result = torch.zeros(target_shape, dtype=tensor.dtype)
                min_shape = [min(s1, s2) for s1, s2 in zip(tensor.shape, target_shape)]
                if len(min_shape) == 2:
                    result[:min_shape[0], :min_shape[1]] = tensor[:min_shape[0], :min_shape[1]]
                elif len(min_shape) == 3:
                    result[:min_shape[0], :min_shape[1], :min_shape[2]] = tensor[:min_shape[0], :min_shape[1], :min_shape[2]]
                return result
        return tensor

    # Fix and load weights
    for key in state_dict:
        if key in model.state_dict():
            target_shape = model.state_dict()[key].shape
            state_dict[key] = fix_dimension(state_dict[key], target_shape)
    
    # Load fixed state dict
    missing, unexpected = model.load_state_dict(state_dict, strict=False)
    print(f"\nModel loaded:")
    print(f"Embedding shape: {emb_shape}")
    print(f"Duration predictor shape: {dur_shape}")
    print(f"Vocabulary size: {len(model.config.characters.characters)}")
    print(f"Missing keys: {len(missing)}")
    print(f"Unexpected keys: {len(unexpected)}")
    
    return model

def test_components(model_path, config_path):
    """Test each VITS component individually"""
    # Load model
    config = VitsConfig()
    config.load_json(config_path)
    model = Vits.init_from_config(config)
    checkpoint = torch.load(model_path, map_location="cpu")
    
    # Initialize model with tokenizer
    model = load_model_carefully(model, checkpoint)
    
    # Verify tokenizer
    if not model.tokenizer:
        raise ValueError("Tokenizer not initialized")
    
    # Test text
    text = "आप अपना चेहरा हमेशा प्रकाश की ओर रखते हैं।"
    try:
        text_inputs = model.tokenizer.text_to_ids(text)
        print(f"Tokenized text length: {len(text_inputs)}")
    except Exception as e:
        print(f"Tokenization error: {str(e)}")
        raise

    text_inputs = torch.LongTensor([text_inputs])
    text_lens = torch.LongTensor([text_inputs.shape[1]])

    with torch.no_grad():
        # Test text encoder
        text_enc_out, m_p, logs_p, _ = model.text_encoder(text_inputs, text_lens)
        print(f"Text encoder output stats: mean={text_enc_out.mean():.3f}, std={text_enc_out.std():.3f}")

        # Generate multiple audio samples
        test_params = [
            {"noise_scale": 0.1, "length_scale": 1.0},
            {"noise_scale": 0.5, "length_scale": 1.0},
            {"noise_scale": 0.667, "length_scale": 1.0}
        ]

        for i, params in enumerate(test_params):
            try:
                o = model.inference(
                    x=text_inputs,
                    x_lengths=text_lens,
                    noise_scale=params["noise_scale"],
                    length_scale=params["length_scale"],
                    noise_scale_w=0.8,
                )
                
                if isinstance(o, tuple):
                    wav = o[0]
                else:
                    wav = o

                # Save audio and plot
                filename = f"test_output_{i}.wav"
                sf.write(filename, wav.squeeze().numpy(), 16000)
                print(f"\nGenerated {filename}")
                print(f"Audio stats (noise={params['noise_scale']}):")
                print(f"  Range: {wav.min():.3f} to {wav.max():.3f}")
                print(f"  Mean: {wav.mean():.3f}")
                print(f"  Std: {wav.std():.3f}")
                
                # Plot waveform
                plt.figure(figsize=(10, 2))
                plt.plot(wav.squeeze().numpy())
                plt.savefig(f"waveform_{i}.png")
                plt.close()

            except Exception as e:
                print(f"Error with params {params}: {str(e)}")

if __name__ == "__main__":
    MODEL_PATH = "./workspace/my-vits-checkpoints/hindi_vits_run-April-14-2025_11+43PM-0000000/best_model_26.pth"
    CONFIG_PATH = "./workspace/tts-dataset/training_config_xtts.json"
    test_components(MODEL_PATH, CONFIG_PATH)
