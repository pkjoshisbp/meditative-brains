import { generateAudioForMessage } from './utils/audioGenerator-test.js';

// Test the simple VITS audio generation
const testText = "Hello, this is a test message for VITS parameter validation.";
const speed = 1.0;  // length_scale parameter
const noise = 0.667;
const noiseW = 0.8;

console.log('Testing VITS parameter passing with test script...');
console.log('Text:', testText);
console.log('Parameters:', { speed, noise, noiseW });

try {
  const filePath = generateAudioForMessage(testText, speed, noise, noiseW);
  console.log('✅ VITS parameter test completed successfully!');
  console.log('File path:', filePath);
} catch (error) {
  console.error('❌ VITS parameter test failed:', error.message);
  console.error('Full error:', error);
}
