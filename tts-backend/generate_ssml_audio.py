import os
import re
import argparse
from TTS.api import TTS
from pydub import AudioSegment

TEMP_DIR = "./temp_audio"
os.makedirs(TEMP_DIR, exist_ok=True)

def parse_enhanced_ssml(text):
    # Handle <repeat times="X">text</repeat>
    repeat_pattern = r'<repeat times="(\d+)">(.*?)</repeat>'
    def expand_repeat(match):
        times = int(match.group(1))
        content = match.group(2).strip()
        return ' '.join([content] * times)
    text = re.sub(repeat_pattern, expand_repeat, text, flags=re.DOTALL)

    # Handle <prosody rate="..."> blocks
    prosody_pattern = r'<prosody rate="(slow|fast)">(.*?)</prosody>'
    segments = []
    pos = 0
    for match in re.finditer(prosody_pattern, text, flags=re.DOTALL):
        start, end = match.span()
        rate, content = match.groups()
        if pos < start:
            segments.append(('text', text[pos:start].strip(), None))
        segments.append(('text', content.strip(), rate))
        pos = end
    if pos < len(text):
        segments.append(('text', text[pos:].strip(), None))

    # Split on <pause> and <pause time="X"/>
    final_segments = []
    pause_pattern = r'<pause(?:\s+time="(\d+(?:\.\d*)?)s")?\s*/?>'
    for seg_type, content, rate in segments:
        parts = re.split(pause_pattern, content)
        i = 0
        while i < len(parts):
            if i % 2 == 0 and parts[i].strip():
                final_segments.append(('text', parts[i].strip(), rate))
            elif i % 2 == 1:
                duration = float(parts[i]) if parts[i] else 1.0
                final_segments.append(('pause', duration, None))
            i += 1

    return final_segments

def generate_audio(text, output_path, base_length=1.2, noise_scale=0.667, noise_scale_w=0.8):
    segments = parse_enhanced_ssml(text)
    final_audio = AudioSegment.silent(duration=0)
    tts = TTS(model_name="tts_models/en/ljspeech/vits", progress_bar=False, gpu=False)

    for idx, (seg_type, content, modifier) in enumerate(segments):
        if seg_type == 'text':
            segment_path = os.path.join(TEMP_DIR, f"segment_{idx}.wav")
            length_scale = base_length
            if modifier == 'slow':
                length_scale = base_length + 0.3
            elif modifier == 'fast':
                length_scale = base_length - 0.3
            tts.tts_to_file(
                text=content,
                file_path=segment_path,
                length_scale=length_scale,
                noise_scale=noise_scale,
                noise_scale_w=noise_scale_w
            )
            audio = AudioSegment.from_wav(segment_path)
            final_audio += audio
        elif seg_type == 'pause':
            final_audio += AudioSegment.silent(duration=int(content * 1000))

    final_audio.export(output_path, format="wav")
    print(f"✅ Audio generated at: {output_path}")

# CLI wrapper
if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--text", help="Text to convert to speech")
    parser.add_argument("--text_file", help="Path to text file instead of direct text")
    parser.add_argument("--output", required=True, help="Output WAV file path")
    parser.add_argument("--length_scale", type=float, default=1.2)
    parser.add_argument("--noise_scale", type=float, default=0.667)
    parser.add_argument("--noise_scale_w", type=float, default=0.8)
    args = parser.parse_args()

    if args.text_file:
        with open(args.text_file, "r", encoding="utf-8") as f:
            text = f.read()
    elif args.text:
        text = args.text
    else:
        raise ValueError("Either --text or --text_file must be provided.")

    generate_audio(text, args.output, args.length_scale, args.noise_scale, args.noise_scale_w)
