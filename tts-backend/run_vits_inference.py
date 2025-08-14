import argparse
import os
from TTS.api import TTS

def get_model_path(speaker_id, language='en'):
    """Get the appropriate model path based on speaker and language"""
    base_dir = os.getcwd()
    
    if language in ['hi', 'hi-IN'] and speaker_id in ['hi-female', 'hi-male']:
        if speaker_id == 'hi-female':
            model_path = os.path.join(base_dir, 'tts_vits_coquiai_HindiFemale', 'hi_female_vits_30hrs.pt')
        else:  # hi-male
            model_path = os.path.join(base_dir, 'tts_vits_coquiai_HindiMale', 'hi_male_vits_30hrs.pt')
        
        if os.path.exists(model_path):
            return model_path
    
    # Default to English VITS model
    return "tts_models/en/ljspeech/vits"

def generate_voice(text, output_path, speaker_id='p225', language='en', length_scale=1.1, noise_scale=0.667, noise_scale_w=0.8):
    model_name_or_path = get_model_path(speaker_id, language)
    
    print(f"Using model: {model_name_or_path}")
    print(f"Speaker: {speaker_id}, Language: {language}")
    
    tts = TTS(model_name=model_name_or_path, progress_bar=False, gpu=False)
    tts.tts_to_file(
        text=text,
        file_path=output_path,
        length_scale=length_scale,
        noise_scale=noise_scale,
        noise_scale_w=noise_scale_w
    )

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--text", help="Text to convert to speech")
    parser.add_argument("--text_file", help="Path to text file instead of direct text")
    parser.add_argument("--output", required=True, help="Output WAV file path")
    parser.add_argument("--speaker", default="p225", help="Speaker ID (p225, p227, hi-female, hi-male)")
    parser.add_argument("--language", default="en", help="Language code (en, hi, hi-IN)")
    parser.add_argument("--length_scale", type=float, default=1.1)
    parser.add_argument("--noise_scale", type=float, default=0.667)
    parser.add_argument("--noise_scale_w", type=float, default=0.8)
    args = parser.parse_args()

    if args.text_file:
        with open(args.text_file, 'r', encoding='utf-8') as f:
            text = f.read()
    elif args.text:
        text = args.text
    else:
        raise ValueError("Either --text or --text_file must be provided.")

    generate_voice(
        text, 
        args.output, 
        args.speaker, 
        args.language,
        args.length_scale, 
        args.noise_scale, 
        args.noise_scale_w
    )
