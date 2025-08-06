import torch
import json
import matplotlib.pyplot as plt
import numpy as np

def analyze_model_issues(model_path, config_path):
    """Analyze VITS model architecture and identify potential issues"""
    # Load config
    with open(config_path) as f:
        config = json.load(f)
    
    # Load model
    checkpoint = torch.load(model_path, map_location='cpu')
    state_dict = checkpoint['model']
    
    issues = []
    recommendations = []
    
    # Check embedding ranges
    emb_weights = state_dict.get('text_encoder.emb.weight', None)
    if emb_weights is not None:
        emb_std = emb_weights.std().item()
        if emb_std < 0.1:
            issues.append("Text embeddings have low variance")
            recommendations.append("Increase embedding initialization scale")
    
    # Check decoder upsample ratios
    decoder_ups = [k for k in state_dict.keys() if 'waveform_decoder.ups' in k]
    if len(decoder_ups) > 0:
        up_weights = [state_dict[k].std().item() for k in decoder_ups]
        if min(up_weights) < 0.01:
            issues.append("Very small upsampling weights")
            recommendations.append("Adjust upsampling initialization")
    
    # Check duration predictor
    dur_weights = [v for k, v in state_dict.items() if 'duration_predictor' in k]
    if dur_weights:
        dur_std = np.mean([w.std().item() for w in dur_weights])
        if dur_std > 0.3:
            issues.append("Duration predictor weights too large")
            recommendations.append("Reduce duration predictor learning rate")
    
    # Save analysis
    with open('analysis_outputs/model_issues.txt', 'w') as f:
        f.write("VITS Model Analysis\n\n")
        f.write("Issues Found:\n")
        for i in issues:
            f.write(f"- {i}\n")
        f.write("\nRecommendations:\n")
        for r in recommendations:
            f.write(f"- {r}\n")
        
        # Add configuration suggestions
        f.write("\nSuggested Configuration Updates:\n")
        if issues:
            f.write("""
{
    "model_args": {
        "use_spectral_norm": true,
        "hidden_channels": 256,
        "encoder_sample_rate": 16000,
        "duration_predictor_channels": 128,
        "prenet_dropout": 0.1
    },
    "optimizer_params": {
        "weight_decay": 0.02
    },
    "batch_size": 16
}
""")

if __name__ == "__main__":
    MODEL_PATH = "./workspace/my-vits-checkpoints/hindi_vits_run-April-14-2025_04+55PM-0000000/best_model_56.pth"
    CONFIG_PATH = "./workspace/tts-dataset/training_config_xtts.json"
    
    analyze_model_issues(MODEL_PATH, CONFIG_PATH)
