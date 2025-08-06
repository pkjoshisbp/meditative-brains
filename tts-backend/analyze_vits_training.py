import torch
import numpy as np
import matplotlib.pyplot as plt
from TTS.tts.configs.vits_config import VitsConfig
from TTS.tts.models.vits import Vits
import os

def analyze_model_components(model_path, config_path):
    """Analyze individual components of the VITS model"""
    try:
        # Load model and setup
        config = VitsConfig()
        config.load_json(config_path)
        model = Vits.init_from_config(config)
        checkpoint = torch.load(model_path, map_location="cpu")
        model.load_state_dict(checkpoint["model"])
        model.eval()
        
        os.makedirs("analysis_outputs", exist_ok=True)
        
        # Open analysis log file
        with open("analysis_outputs/model_analysis.txt", "w") as f:
            # Test full pipeline
            text = "आप अपना चेहरा हमेशा प्रकाश की ओर रखते हैं।"
            text_inputs = model.tokenizer.text_to_ids(text)
            text_inputs = torch.LongTensor([text_inputs])
            text_lens = torch.LongTensor([text_inputs.shape[1]])
            
            with torch.no_grad():
                # Analyze text encoder
                text_enc_out, m_p, logs_p, _ = model.text_encoder(text_inputs, text_lens)
                
                # Plot component activations
                plot_activations(text_enc_out, m_p, logs_p, "analysis_outputs/activations.png")
                
                # Test full generation pipeline using model's tts method
                try:
                    # Fix inference call
                    audio = model.inference(
                        x=text_inputs,
                        x_lengths=text_lens,
                        noise_scale=0.667,
                        length_scale=1.0,
                        noise_scale_w=0.8,
                    )
                    print("\nFull Pipeline Analysis:")
                    if isinstance(audio, tuple):
                        audio = audio[0]
                    print(f"Generated audio shape: {audio.shape}")
                    print(f"Audio statistics:")
                    print(f"  - Range: {audio.min():.3f} to {audio.max():.3f}")
                    print(f"  - Mean: {audio.mean():.3f}")
                    print(f"  - Std: {audio.std():.3f}")
                    
                    # Save sample audio
                    if audio is not None:
                        import soundfile as sf
                        sf.write("analysis_outputs/test_output.wav", audio.squeeze().numpy(), 16000)
                except Exception as e:
                    print(f"\nInference pipeline error: {str(e)}")
                
                # Component-wise analysis
                f.write("\nNetwork Architecture Analysis:\n")
                print("\nSaving detailed analysis to analysis_outputs/model_analysis.txt")
                
                # Track layer statistics for visualization
                layer_stats = {
                    'encoder': [],
                    'decoder': [],
                    'discriminator': []
                }
                
                for name, module in model.named_modules():
                    try:
                        if hasattr(module, 'weight') and not isinstance(module.weight, torch.nn.parameter.UninitializedParameter):
                            w = module.weight
                            if hasattr(w, 'data'):
                                w = w.data
                                stats = {
                                    'name': name,
                                    'shape': list(w.shape),
                                    'min': w.min().item(),
                                    'max': w.max().item(),
                                    'mean': w.mean().item(),
                                    'std': w.std().item()
                                }
                                
                                # Log to file
                                f.write(f"\n{name}:\n")
                                f.write(f"  Shape: {w.shape}\n")
                                f.write(f"  Range: {w.min():.3f} to {w.max():.3f}\n")
                                f.write(f"  Mean: {w.mean():.3f}\n")
                                f.write(f"  Std: {w.std():.3f}\n")
                                
                                # Categorize for visualization
                                if 'encoder' in name:
                                    layer_stats['encoder'].append(stats)
                                elif 'decoder' in name:
                                    layer_stats['decoder'].append(stats)
                                elif 'disc' in name:
                                    layer_stats['discriminator'].append(stats)
                                
                    except Exception as e:
                        continue
                
                # Plot layer statistics
                plot_layer_statistics(layer_stats, "analysis_outputs/layer_statistics.png")
                
                # Save model summary
                f.write("\nModel Summary:\n")
                f.write(f"Total parameters: {sum(p.numel() for p in model.parameters()):,}\n")
                f.write(f"Trainable parameters: {sum(p.numel() for p in model.parameters() if p.requires_grad):,}\n")
                    
    except Exception as e:
        print(f"Error during analysis: {str(e)}")
        import traceback
        print(traceback.format_exc())

def plot_activations(text_enc_out, m_p, logs_p, save_path):
    """Plot detailed activation patterns"""
    fig, ((ax1, ax2), (ax3, ax4)) = plt.subplots(2, 2, figsize=(15, 10))
    
    # Text encoder patterns
    im1 = ax1.imshow(text_enc_out[0].T.numpy(), aspect='auto')
    ax1.set_title("Text Encoder Patterns")
    plt.colorbar(im1, ax=ax1)
    
    # Mean projection distribution
    ax2.hist(m_p.numpy().flatten(), bins=50)
    ax2.set_title("Mean Projection Distribution")
    
    # Log sigma patterns
    im3 = ax3.imshow(logs_p[0].T.numpy(), aspect='auto')
    ax3.set_title("Log Sigma Patterns")
    plt.colorbar(im3, ax=ax3)
    
    # Activation correlation
    corr = np.corrcoef(text_enc_out[0].numpy())
    im4 = ax4.imshow(corr)
    ax4.set_title("Encoder Output Correlation")
    plt.colorbar(im4, ax=ax4)
    
    plt.tight_layout()
    plt.savefig(save_path)
    plt.close()

def plot_layer_statistics(stats, save_path):
    """Plot statistics of different model components"""
    fig, ((ax1, ax2), (ax3, ax4)) = plt.subplots(2, 2, figsize=(15, 10))
    
    # Plot weight distributions
    for component, layers in stats.items():
        if layers:
            means = [l['mean'] for l in layers]
            stds = [l['std'] for l in layers]
            ax1.scatter(means, stds, label=component, alpha=0.5)
    ax1.set_xlabel('Mean')
    ax1.set_ylabel('Standard Deviation')
    ax1.set_title('Weight Statistics')
    ax1.legend()
    
    # Plot layer sizes
    for component, layers in stats.items():
        if layers:
            sizes = [np.prod(l['shape']) for l in layers]
            ax2.hist(sizes, label=component, alpha=0.5, bins=30)
    ax2.set_xlabel('Layer Size (parameters)')
    ax2.set_ylabel('Count')
    ax2.set_title('Layer Size Distribution')
    ax2.legend()
    
    # Plot value ranges
    for component, layers in stats.items():
        if layers:
            ranges = [l['max'] - l['min'] for l in layers]
            ax3.hist(ranges, label=component, alpha=0.5, bins=30)
    ax3.set_xlabel('Value Range')
    ax3.set_ylabel('Count')
    ax3.set_title('Weight Range Distribution')
    ax3.legend()
    
    plt.tight_layout()
    plt.savefig(save_path)
    plt.close()

if __name__ == "__main__":
    MODEL_PATH = "./workspace/my-vits-checkpoints/hindi_vits_run-April-14-2025_11+43PM-0000000/best_model_26.pth"
    CONFIG_PATH = "./workspace/tts-dataset/training_config_xtts.json"
    
    analyze_model_components(MODEL_PATH, CONFIG_PATH)
