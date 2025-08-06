import argparse
from TTS.api import TTS

def generate_voice(text, output_path, length_scale=1.1, noise_scale=0.667, noise_scale_w=0.8):
    tts = TTS(model_name="tts_models/en/ljspeech/vits", progress_bar=False, gpu=False)
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

    generate_voice(text, args.output, args.length_scale, args.noise_scale, args.noise_scale_w)
