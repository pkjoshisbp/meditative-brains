import { generateAudioForMessage } from './utils/audioGenerator.js';

async function testVitsGeneration() {
  console.log('Testing VITS audio generation...');
  
  const testText = "Hello, this is a test message for VITS audio generation.";
  const options = {
    engine: 'vits',
    language: 'en-US',
    speaker: 'vits-en',
    category: 'test',
    speed: 1.0,
    noise: 0.667,
    noiseW: 0.8
  };

  try {
    const result = await generateAudioForMessage(testText, options);
    console.log('✅ VITS audio generated successfully!');
    console.log('Audio URL:', result.audioUrl);
    console.log('File path:', result.filePath);
  } catch (error) {
    console.error('❌ VITS audio generation failed:', error.message);
    console.error('Full error:', error);
  }
}

testVitsGeneration();
