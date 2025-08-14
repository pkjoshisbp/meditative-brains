import { generateAudioForMessage } from './utils/audioGenerator-copy.js';

// Test the simple VITS audio generation
const testText = "Hello, this is a test message for VITS audio generation using the copy file.";
const speed = 1.0;  // length_scale parameter
const noise = 0.667;
const noiseW = 0.8;

console.log('Testing VITS audio generation with audioGenerator-copy.js...');
console.log('Text:', testText);
console.log('Parameters:', { speed, noise, noiseW });

try {
  const filePath = generateAudioForMessage(testText, speed, noise, noiseW);
  console.log('✅ VITS audio generated successfully!');
  console.log('File path:', filePath);
} catch (error) {
  console.error('❌ VITS audio generation failed:', error.message);
  console.error('Full error:', error);
}
