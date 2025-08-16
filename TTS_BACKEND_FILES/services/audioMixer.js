const ffmpeg = require('fluent-ffmpeg');
const ffmpegPath = require('ffmpeg-static');
const path = require('path');
const fs = require('fs').promises;
const { v4: uuidv4 } = require('uuid');

// Set FFmpeg path
ffmpeg.setFfmpegPath(ffmpegPath);

class AudioMixer {
    constructor() {
        this.tempDir = path.join(__dirname, '../temp');
        this.publicTempDir = path.join(__dirname, '../public/temp-audio');
        this.backgroundMusicDir = path.join(__dirname, '../background-music');
        this.ensureDirectories();
    }

    async ensureDirectories() {
        try {
            await fs.mkdir(this.tempDir, { recursive: true });
            await fs.mkdir(this.publicTempDir, { recursive: true });
            await fs.mkdir(this.backgroundMusicDir, { recursive: true });
        } catch (error) {
            console.error('Failed to create directories:', error);
        }
    }

    /**
     * Mix TTS audio with background music
     */
    async mixWithBackground(ttsAudioPath, backgroundMusicUrl, previewDuration = 30) {
        const outputId = uuidv4();
        const outputPath = path.join(this.publicTempDir, `preview_${outputId}.mp3`);

        return new Promise((resolve, reject) => {
            const command = ffmpeg();

            // Add TTS audio as first input
            command.input(ttsAudioPath);

            // Handle background music input
            if (backgroundMusicUrl && backgroundMusicUrl.startsWith('http')) {
                // External URL
                command.input(backgroundMusicUrl);
            } else if (backgroundMusicUrl) {
                // Local file
                const localPath = this.getLocalBackgroundMusicPath(backgroundMusicUrl);
                if (localPath) {
                    command.input(localPath);
                } else {
                    // If background music not found, just process TTS
                    return this.processAudioOnly(ttsAudioPath, previewDuration, outputPath, resolve, reject);
                }
            } else {
                // No background music, just process TTS
                return this.processAudioOnly(ttsAudioPath, previewDuration, outputPath, resolve, reject);
            }

            // Complex filter for mixing
            command.complexFilter([
                '[0:a]volume=1.0[tts]',           // TTS at full volume
                '[1:a]volume=0.2,afade=t=in:st=0:d=2,afade=t=out:st=' + (previewDuration-2) + ':d=2[bg]', // Background music at 20% with fade
                '[tts][bg]amix=inputs=2:duration=shortest:dropout_transition=0[mixed]'
            ], 'mixed');

            command
                .duration(previewDuration)
                .audioCodec('mp3')
                .audioBitrate('128k')
                .audioFrequency(44100)
                .audioChannels(2)
                .output(outputPath)
                .on('end', () => {
                    resolve({
                        outputPath,
                        publicUrl: `/temp-audio/preview_${outputId}.mp3`,
                        duration: previewDuration,
                        fileId: outputId,
                        hasMixedAudio: true
                    });
                })
                .on('error', (error) => {
                    console.error('Audio mixing error:', error);
                    reject(new Error(`Audio mixing failed: ${error.message}`));
                })
                .on('progress', (progress) => {
                    console.log(`Mixing progress: ${Math.round(progress.percent || 0)}%`);
                });

            command.run();
        });
    }

    /**
     * Process TTS audio only (no background music)
     */
    async processAudioOnly(ttsAudioPath, previewDuration, outputPath, resolve, reject) {
        ffmpeg(ttsAudioPath)
            .duration(previewDuration)
            .audioCodec('mp3')
            .audioBitrate('128k')
            .audioFrequency(44100)
            .output(outputPath)
            .on('end', () => {
                const outputId = path.basename(outputPath, '.mp3').replace('preview_', '');
                resolve({
                    outputPath,
                    publicUrl: `/temp-audio/preview_${outputId}.mp3`,
                    duration: previewDuration,
                    fileId: outputId,
                    hasMixedAudio: false
                });
            })
            .on('error', reject)
            .run();
    }

    /**
     * Get local background music file path
     */
    getLocalBackgroundMusicPath(backgroundMusicUrl) {
        try {
            // Extract filename from URL
            const urlParts = backgroundMusicUrl.split('/');
            const filename = urlParts[urlParts.length - 1];
            const localPath = path.join(this.backgroundMusicDir, filename);
            
            // Check if file exists synchronously (for this use case)
            try {
                require('fs').accessSync(localPath);
                return localPath;
            } catch {
                console.warn(`Background music file not found: ${localPath}`);
                return null;
            }
        } catch (error) {
            console.error('Error processing background music path:', error);
            return null;
        }
    }

    /**
     * Trim audio to specified duration
     */
    async trimAudio(inputPath, duration) {
        const outputId = uuidv4();
        const outputPath = path.join(this.publicTempDir, `trimmed_${outputId}.mp3`);

        return new Promise((resolve, reject) => {
            ffmpeg(inputPath)
                .duration(duration)
                .audioCodec('mp3')
                .audioBitrate('128k')
                .output(outputPath)
                .on('end', () => {
                    resolve({
                        outputPath,
                        publicUrl: `/temp-audio/trimmed_${outputId}.mp3`,
                        duration,
                        fileId: outputId
                    });
                })
                .on('error', reject)
                .run();
        });
    }

    /**
     * Clean up temporary files older than specified hours
     */
    async cleanupTempFiles(maxAgeHours = 2) {
        try {
            const files = await fs.readdir(this.publicTempDir);
            const now = Date.now();
            const maxAge = maxAgeHours * 60 * 60 * 1000;
            let cleanedCount = 0;

            for (const file of files) {
                if (file.startsWith('preview_') || file.startsWith('trimmed_')) {
                    try {
                        const filePath = path.join(this.publicTempDir, file);
                        const stats = await fs.stat(filePath);
                        
                        if (now - stats.mtime.getTime() > maxAge) {
                            await fs.unlink(filePath);
                            cleanedCount++;
                            console.log(`Cleaned up temporary file: ${file}`);
                        }
                    } catch (error) {
                        console.error(`Error cleaning up file ${file}:`, error);
                    }
                }
            }
            
            if (cleanedCount > 0) {
                console.log(`Cleanup completed: ${cleanedCount} files removed`);
            }
        } catch (error) {
            console.error('Cleanup error:', error);
        }
    }

    /**
     * Get audio file info
     */
    async getAudioInfo(filePath) {
        return new Promise((resolve, reject) => {
            ffmpeg.ffprobe(filePath, (err, metadata) => {
                if (err) {
                    reject(err);
                } else {
                    resolve({
                        duration: metadata.format.duration,
                        bitrate: metadata.format.bit_rate,
                        format: metadata.format.format_name,
                        size: metadata.format.size
                    });
                }
            });
        });
    }

    /**
     * Validate audio file
     */
    async validateAudioFile(filePath) {
        try {
            await this.getAudioInfo(filePath);
            return true;
        } catch (error) {
            console.error(`Invalid audio file: ${filePath}`, error);
            return false;
        }
    }
}

module.exports = AudioMixer;
