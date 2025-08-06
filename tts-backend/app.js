import dotenv from 'dotenv';
dotenv.config();
import express from 'express';
import mongoose from 'mongoose';
import fs from 'fs';
import https from 'https';
import bodyParser from 'body-parser';
import categoryRouter from './routes/category.js';
import userRouter from './routes/user.js';
import motivationMessageRouter from './routes/motivationMessage.js';
import authRouter from './routes/auth.js'; 
import audioRouter from './routes/audio.js';
import languageRouter from './routes/language.js';
import attentionGuideRouter from './routes/attentionGuide.js';
import logsRouter from './routes/logs.js';
import path from 'path';
import logger from './utils/logger.js';

// Define HTTPS options with your SSL certificate and key
const options = {
    cert: fs.readFileSync('/var/www/clients/client1/web51/ssl/motivation.mywebsolutions.co.in-le.crt'),
    key: fs.readFileSync('/var/www/clients/client1/web51/ssl/motivation.mywebsolutions.co.in-le.key')
};

const app = express();

app.use(bodyParser.json({ limit: '10mb' }));
app.use(express.json({ limit: '10mb' }));

// Proper request logging middleware
app.use((req, res, next) => {
    logger.info(`[REQUEST] ${req.method} ${req.originalUrl}`);
    next();
});

// Error handling middleware
app.use((err, req, res, next) => {
    logger.error(`[ERROR] Unhandled error:`, err);
    res.status(500).json({ 
        success: false, 
        error: 'Internal server error', 
        message: err.message 
    });
});

// Mount routers
app.use("/api/motivationMessage", motivationMessageRouter);
app.use("/api", motivationMessageRouter);
app.use("/api/category", categoryRouter);
app.use("/api/user", userRouter);
app.use("/api/auth", authRouter);
app.use("/api/audio", audioRouter);
app.use("/api/language", languageRouter);
app.use("/api/attention-guide", attentionGuideRouter);
app.use("/api/logs", logsRouter); // Add this line for logs router

// Add static file serving for audio files
const audioCachePath = path.join(process.cwd(), 'audio-cache');
app.use('/audio-cache', express.static(audioCachePath));

// Add static file serving for flutter logs
const flutterLogsPath = path.join(process.cwd(), 'flutter_logs');
app.use('/flutter-logs', express.static(flutterLogsPath));

// Connect to MongoDB
mongoose.connect('mongodb://pawan:pragati123..@127.0.0.1:27017/motivation')
    .then(() => {
        // Create directories if they don't exist
        if (!fs.existsSync(audioCachePath)) {
            fs.mkdirSync(audioCachePath, { recursive: true });
            logger.info(`Created audio cache directory: ${audioCachePath}`);
        }
        
        // Create the HTTPS server and start listening on port 3000
        https.createServer(options, app).listen(3000, () => {
            logger.info('Server running on port 3000 with HTTPS');
            logger.info(`Audio cache directory: ${audioCachePath}`);
        });
    })
    .catch(err => logger.error('MongoDB connection error:', err));

// Add a 404 handler for API routes
app.use('/api/*', (req, res) => {
    logger.error(`API endpoint not found: ${req.originalUrl}`);
    res.status(404).json({ success: false, error: 'Endpoint not found' });
});

