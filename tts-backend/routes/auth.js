// routes/auth.js
import express from 'express';
import bcrypt from 'bcrypt';
import jwt from 'jsonwebtoken';
import mongoose from 'mongoose';
import User from '../models/User.js';
import Device from '../models/Device.js';
import checkDevice from '../middleware/checkDevice.js';
import { authLogger } from '../utils/logger.js';

const authRouter = express.Router();
const secretKey = 'abdefredgislxoselshzi125ax7';

// User login
authRouter.post('/login', checkDevice, async (req, res) => {
    // Handle both body and query parameters
    const username = req.body.username || req.query.username;
    const password = req.body.password || req.query.password;
    const deviceId = req.body.deviceId || req.query.deviceId;

    authLogger.info('Auth route - Login request received', { 
        username,
        deviceId: deviceId || 'NOT_PROVIDED',
        ip: req.ip,
        userAgent: req.get('User-Agent'),
        source: req.body.username ? 'body' : 'query'
    });

    try {
        authLogger.debug('Auth route - Searching for user', { username });
        authLogger.debug('Auth route - MongoDB connection state', { 
            readyState: mongoose.connection.readyState,
            host: mongoose.connection.host,
            name: mongoose.connection.name
        });
        
        const user = await User.findOne({ username });
        authLogger.debug('Auth route - User query result', { 
            found: !!user,
            username,
            userObject: user ? { id: user._id, username: user.username } : null
        });
        
        if (!user) {
            authLogger.warn('Auth route - User not found', { username });
            return res.status(400).json({ message: 'Invalid username or password' });
        }

        authLogger.debug('Auth route - User found, validating password', { 
            username: user.username,
            userId: user._id
        });
        
        const isPasswordValid = await bcrypt.compare(password, user.password);
        
        if (!isPasswordValid) {
            authLogger.warn('Auth route - Password validation failed', { 
                username: user.username,
                userId: user._id
            });
            return res.status(400).json({ message: 'Invalid username or password' });
        }

        authLogger.info('Auth route - Password validated successfully', { 
            username: user.username,
            userId: user._id
        });

        authLogger.debug('Auth route - Generating JWT token', { 
            username: user.username,
            userId: user._id
        });
        const token = jwt.sign({ userId: user._id }, secretKey, { expiresIn: '1h' });

        // Only register device if deviceId is provided
        if (deviceId) {
            authLogger.info('Auth route - Registering device', { 
                username: user.username,
                userId: user._id,
                deviceId
            });
            
            const deviceRecord = await Device.findOneAndUpdate(
                { userId: user._id, deviceId },
                { 
                    userId: user._id, 
                    deviceId,
                    username: user.username
                },
                { upsert: true, new: true }
            );
            
            authLogger.info('Auth route - Device registered successfully', { 
                username: user.username,
                userId: user._id,
                deviceId,
                deviceRecordId: deviceRecord._id
            });
        } else {
            authLogger.debug('Auth route - No deviceId provided, skipping device registration');
        }

        authLogger.info('Auth route - Login successful', { 
            username: user.username,
            userId: user._id,
            deviceId: deviceId || 'NONE'
        });
        
        res.status(200).json({ token });
    } catch (error) {
        authLogger.error('Auth route - Error occurred', { 
            error: error.message,
            stack: error.stack,
            username,
            deviceId
        });
        res.status(500).json({ message: error.message });
    }
});

export default authRouter;
