import torch
import numpy as np
import librosa
import soundfile as sf
import matplotlib.pyplot as plt
from TTS.tts.configs.vits_config import VitsConfig
from TTS.tts.models.vits import Vits

def analyze_audio_files():
    """Analyze training audio files"""
    paths = [
        "/var/www/clients/client1/web51/web/workspace/tts-dataset/wavs/00001.wav",
        "/var/www/clients/client1/web51/web/workspace/tts-dataset/wavs/00002.wav",
        "/var/www/clients/client1/web51/web/workspace/tts-dataset/wavs/00003.wav"
    ]
    
    fig, axes = plt.subplots(3, 2, figsize=(15, 10))
    for idx, path in enumerate(paths):
        y, sr = librosa.load(path, sr=16000)
        
        # Plot waveform
        axes[idx, 0].plot(y)
        axes[idx, 0].set_title(f'Waveform {idx+1}')
        
        # Plot mel spectrogram
        mel = librosa.feature.melspectrogram(y=y, sr=sr, n_mels=80)
        mel_db = librosa.power_to_db(mel, ref=np.max)
        librosa.display.specshow(mel_db, sr=sr, ax=axes[idx, 1])
        axes[idx, 1].set_title(f'Mel Spectrogram {idx+1}')
        
        print(f"\nAudio {idx+1} stats:")
        print(f"Duration: {len(y)/sr:.2f}s")
        print(f"Range: {y.min():.3f} to {y.max():.3f}")
        print(f"Mean: {y.mean():.3f}")
        print(f"Std: {y.std():.3f}")
    
    plt.tight_layout()
    plt.savefig('audio_analysis.png')
    plt.close()

def test_model_output():
    """Test model generation"""
    config = VitsConfig()
    config.load_json("/var/www/clients/client1/web51/web/workspace/tts-dataset/training_config_xtts.json")
    checkpoint = torch.load("/var/www/clients/client1/web51/web/workspace/my-vits-checkpoints/vits_test-April-15-2025_02+18AM-0000000/best_model_21.pth", map_location='cpu')
    
    model = Vits.init_from_config(config)
    model.load_state_dict(checkpoint["model"])
    model.eval()
    
    # Print model info safely
    print("\nModel configuration:")
    print(f"Config hidden channels: {config.model_args.hidden_channels}")
    print(f"Config num chars: {config.model_args.num_chars}")
    try:
        print(f"Encoder embedding shape: {model.text_encoder.emb.weight.shape}")
        print(f"Duration predictor size: {model.duration_predictor.proj.weight.shape}")
    except AttributeError as e:
        print(f"Could not access model component: {str(e)}")
    
    # Debug tokenizer
    print("\nTokenizer info:")
    if hasattr(model, 'tokenizer'):
        print(f"Tokenizer type: {type(model.tokenizer)}")
        if hasattr(model.tokenizer, 'characters'):
            char_set = getattr(model.tokenizer.characters, 'characters', [])
            print(f"Character set: {char_set}")
            print(f"Number of characters: {len(char_set) if char_set else 'unknown'}")
        if hasattr(model.tokenizer, 'print_logs'):
            model.tokenizer.print_logs()
    else:
        print("No tokenizer found")
    
    # Test tokenization
    with torch.no_grad():
        text = "hello this is a test sentence"
        try:
            tokens = model.tokenizer.text_to_ids(text)
            print(f"\nTokenization test:")
            print(f"Input text: {text}")
            print(f"Token IDs: {tokens}")
            print(f"Number of tokens: {len(tokens)}")
            
            inputs = torch.LongTensor([tokens])
            lengths = torch.LongTensor([inputs.shape[1]])
            
            # Print input details
            print(f"\nModel input details:")
            print(f"Input shape: {inputs.shape}")
            print(f"Length shape: {lengths.shape}")
            print(f"Max token ID: {inputs.max().item()}")
            print(f"Min token ID: {inputs.min().item()}")
            
            # Test direct inference
            try:
                print("\nTrying different inference approaches...")
                
                # Try text_encoder first
                text_outputs = model.text_encoder(inputs, lengths)
                print("\nText encoder output shapes:")
                for i, t in enumerate(text_outputs):
                    if isinstance(t, torch.Tensor):
                        print(f"Output {i}: {t.shape}")

                # Try direct forward pass
                try:
                    outputs = model.forward(
                        inputs,  # x
                        lengths,  # x_lengths
                        None,    # y 
                        None,    # y_lengths
                        None     # waveform
                    )
                    print("\nForward pass successful")
                except Exception as e:
                    print(f"\nForward pass failed: {str(e)}")
                    
                    try:
                        # Try synthesize
                        print("\nTrying synthesize...")
                        outputs = model.synthesize(
                            text=[text],
                            config=model.config,
                            speaker_id=None,
                            temperature=0.667,
                            length_scale=1.0,
                            noise_scale=0.8
                        )
                    except Exception as e:
                        print(f"\nSynthesize failed: {str(e)}")
                        
                        # Final attempt with basic forward
                        print("\nTrying basic forward...")
                        outputs = model(inputs)
                
                if outputs is not None:
                    print("\nSuccessfully generated output")
                    if isinstance(outputs, tuple):
                        wav = outputs[0]
                        print(f"Output tuple length: {len(outputs)}")
                    else:
                        wav = outputs
                    
                    print(f"\nOutput waveform stats:")
                    print(f"Shape: {wav.shape}")
                    print(f"Range: {wav.min():.3f} to {wav.max():.3f}")
                    print(f"Mean: {wav.mean():.3f}")
                    print(f"Std: {wav.std():.3f}")
                    
                    wav_np = wav.squeeze().numpy()
                    
                    # Plot more detailed analysis
                    plt.figure(figsize=(15, 10))
                    plt.subplot(2, 1, 1)
                    plt.plot(wav_np)
                    plt.title('Generated Waveform')
                    
                    plt.subplot(2, 1, 2)
                    mel = librosa.feature.melspectrogram(y=wav_np, sr=16000, n_mels=80)
                    mel_db = librosa.power_to_db(mel, ref=np.max)
                    librosa.display.specshow(mel_db, sr=16000, y_axis='mel')
                    plt.colorbar(format='%+2.0f dB')
                    plt.title('Generated Mel Spectrogram')
                    
                    plt.tight_layout()
                    plt.savefig('generated_analysis.png')
                    plt.close()
                    
                    sf.write('test_output.wav', wav_np, 16000)
                else:
                    print("No output generated")
                    
            except Exception as e:
                print(f"All inference attempts failed: {str(e)}")
                print("\nModel attributes:")
                for attr in dir(model):
                    if not attr.startswith('_'):
                        try:
                            val = getattr(model, attr)
                            if not callable(val):
                                print(f"{attr}: {val}")
                        except:
                            pass
                
        except Exception as e:
            print(f"Error during processing: {str(e)}")
            import traceback
            print(traceback.format_exc())
            return

if __name__ == "__main__":
    print("Analyzing training audio files...")
    analyze_audio_files()
    print("\nTesting model output...")
    test_model_output()
