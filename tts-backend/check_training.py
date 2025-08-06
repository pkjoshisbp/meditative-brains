import os
import torch
import librosa
import numpy as np
from glob import glob
from TTS.tts.configs.vits_config import VitsConfig
from TTS.tts.models.vits import Vits

def check_model_weights(model_path):
    """Check if model weights are properly loaded and have expected values"""
    try:
        checkpoint = torch.load(model_path, map_location=torch.device('cpu'))
        
        print("\nModel Info:")
        if 'model' in checkpoint:
            state_dict = checkpoint['model']
            print(f"✓ Model state found")
            
            # Add detailed component check
            components = {
                'text_encoder': ['encoder.text_encoder', 'emb'],
                'flow': ['flow.flows', 'flow.projector'],
                'decoder': ['dec', 'decoder'],
                'discriminator': ['disc', 'discriminator']
            }
            
            print("\nComponent Check:")
            for comp_name, patterns in components.items():
                found = any(any(pattern in key for key in state_dict.keys()) for pattern in patterns)
                print(f"{'✓' if found else '❌'} {comp_name}")
                
            # Add parameter distribution analysis
            param_stats = {}
            for name, param in state_dict.items():
                if isinstance(param, torch.Tensor):
                    param_stats[name] = {
                        'mean': param.mean().item(),
                        'std': param.std().item(),
                        'min': param.min().item(),
                        'max': param.max().item()
                    }
            
            print("\nParameter Statistics:")
            concerning_params = [name for name, stats in param_stats.items() 
                               if abs(stats['mean']) > 10 or stats['std'] > 10]
            if concerning_params:
                print("⚠️ Parameters with unusual statistics:")
                for name in concerning_params[:3]:  # Show first 3
                    print(f"  - {name}")
        else:
            state_dict = checkpoint
            print("⚠️ Direct state dict found (no model key)")
            
        print(f"Number of state elements: {len(state_dict)}")
        
        # Analyze model architecture
        expected_keys = ['emb', 'encoder', 'decoder', 'disc']
        missing_components = [k for k in expected_keys if not any(k in key for key in state_dict.keys())]
        if missing_components:
            print(f"❌ Missing model components: {missing_components}")
        
        # Count parameters and check values
        total_params = 0
        has_nan = False
        has_zero = False
        for name, param in state_dict.items():
            if isinstance(param, torch.Tensor):
                total_params += param.numel()
                if torch.isnan(param).any():
                    print(f"❌ WARNING: NaN values found in {name}")
                    has_nan = True
                if torch.all(param == 0):
                    print(f"❌ WARNING: All zeros found in {name}")
                    has_zero = True
                if param.abs().max() > 100:
                    print(f"⚠️ Large values found in {name}: {param.abs().max():.2f}")
        
        print(f"Total parameters: {total_params:,}")
        
        # Check training state
        if 'step' in checkpoint:
            print(f"Training steps completed: {checkpoint['step']}")
        if 'epoch' in checkpoint:
            print(f"Epochs completed: {checkpoint['epoch']}")
            
        # Analyze loss history if available
        if 'loss_history' in checkpoint:
            losses = checkpoint['loss_history']
            print("\nLoss History:")
            for key, value in losses.items():
                if isinstance(value, (list, tuple)):
                    print(f"{key}: {value[-1]:.4f} (latest)")
                else:
                    print(f"{key}: {value:.4f}")
        
        # Check optimizer state
        if 'optimizer' in checkpoint:
            print("\nOptimizer state found")
            if 'param_groups' in checkpoint['optimizer']:
                for group in checkpoint['optimizer']['param_groups']:
                    print(f"Learning rate: {group.get('lr', 'N/A')}")
        
    except Exception as e:
        print(f"Error loading model: {str(e)}")
        raise

def check_audio_dataset(dataset_path):
    """Verify audio files in the dataset"""
    print("\nChecking Audio Dataset:")
    audio_files = glob(os.path.join(dataset_path, "**/*.wav"), recursive=True)
    
    if not audio_files:
        print("❌ No audio files found!")
        return
    
    print(f"Found {len(audio_files)} audio files")
    
    # Check first few files
    sample_rates = []
    for audio_file in audio_files[:5]:
        try:
            y, sr = librosa.load(audio_file, sr=None)  # Load with native sample rate
            duration = librosa.get_duration(y=y, sr=sr)
            print(f"\n{os.path.basename(audio_file)}:")
            print(f"✓ Sample rate: {sr} Hz")
            print(f"✓ Duration: {duration:.2f} seconds")
            print(f"✓ Max amplitude: {np.abs(y).max():.2f}")
            sample_rates.append(sr)
            
            if np.abs(y).max() < 0.1:
                print("⚠️ Warning: Audio might be too quiet")
            if sr != 16000:
                print("⚠️ Warning: Sample rate should be 16000 Hz")
                
        except Exception as e:
            print(f"Error processing {audio_file}: {str(e)}")
    
    # Check sample rate consistency
    if len(set(sample_rates)) > 1:
        print("\n❌ ERROR: Inconsistent sample rates detected!")
        print(f"Found rates: {set(sample_rates)}")

def check_training_progress(checkpoint_dir):
    """Analyze training checkpoints"""
    print("\nAnalyzing Training Progress:")
    checkpoints = glob(os.path.join(checkpoint_dir, "*.pth"))
    
    # Sort checkpoints by parsing numbers from filenames
    def get_checkpoint_number(filename):
        try:
            # Handle both checkpoint_XX.pth and best_model_XX.pth formats
            if 'best_model' in filename:
                return int(filename.split('_')[-1].split('.')[0])
            else:
                return int(filename.split('checkpoint_')[-1].split('.')[0])
        except:
            return 0
    
    checkpoints.sort(key=get_checkpoint_number)
    
    if not checkpoints:
        print("❌ No checkpoints found!")
        return
        
    print(f"Found {len(checkpoints)} checkpoints")
    print(f"Checkpoint progression:")
    for ckpt in checkpoints[-5:]:  # Show last 5 checkpoints
        print(f"  - {os.path.basename(ckpt)}")
    
    # Load and analyze latest checkpoint
    try:
        latest = torch.load(checkpoints[-1], map_location=torch.device('cpu'))
        if 'model' in latest:
            print("\nLatest Checkpoint Analysis:")
            if 'step' in latest:
                print(f"Training steps: {latest['step']}")
            if 'epoch' in latest:
                print(f"Current epoch: {latest['epoch']}")
            
            # Check model parameters statistics
            state_dict = latest['model']
            print("\nParameters Statistics:")
            total_params = sum(p.numel() for p in state_dict.values() if isinstance(p, torch.Tensor))
            zero_params = sum(torch.sum(p == 0).item() for p in state_dict.values() if isinstance(p, torch.Tensor))
            print(f"Zero parameters: {zero_params/total_params*100:.2f}%")
            
            # Check for extreme values
            max_val = max(p.abs().max().item() for p in state_dict.values() if isinstance(p, torch.Tensor))
            print(f"Maximum parameter value: {max_val:.2f}")
            
            # Analyze loss trend
            if 'loss_history' in latest:
                print("\nLoss Trend Analysis:")
                loss_history = latest['loss_history']
                for key, values in loss_history.items():
                    if isinstance(values, (list, tuple)) and len(values) > 1:
                        print(f"{key}: {values[-1]:.4f} (latest), {values[0]:.4f} (initial)")
                        trend = "decreasing" if values[-1] < values[0] else "increasing"
                        print(f"Trend: {trend}")
            
    except Exception as e:
        print(f"Error analyzing latest checkpoint: {str(e)}")

if __name__ == "__main__":
    MODEL_PATH = "./workspace/my-vits-checkpoints/hindi_vits_run-April-14-2025_04+55PM-0000000/best_model_56.pth"
    DATASET_PATH = "./workspace/tts-dataset"
    CHECKPOINT_DIR = "./workspace/my-vits-checkpoints/hindi_vits_run-April-14-2025_04+55PM-0000000"
    
    print("=== VITS Training Diagnostics ===")
    check_model_weights(MODEL_PATH)
    check_audio_dataset(DATASET_PATH)
    check_training_progress(CHECKPOINT_DIR)
