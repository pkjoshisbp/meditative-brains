// middleware/checkDevice.js
import Device from '../models/Device.js';
import User from '../models/User.js';
import { authLogger } from '../utils/logger.js';

const MAX_DEVICES = 2; // Set the maximum number of devices allowed per user

const checkDevice = async (req, res, next) => {
    // Handle both body and query parameters
    const username = req.body.username || req.query.username;
    const deviceId = req.body.deviceId || req.query.deviceId;

    authLogger.info('checkDevice middleware - Request received', { 
        username, 
        deviceId: deviceId || 'NOT_PROVIDED',
        ip: req.ip,
        userAgent: req.get('User-Agent'),
        source: req.body.username ? 'body' : 'query'
    });

    // If no deviceId is provided, we can either skip device checking or use a default
    if (!deviceId) {
        authLogger.info('checkDevice middleware - No deviceId provided, skipping device check');
        return next();
    }

    try {
        // First find the user by username to get the userId
        authLogger.debug('checkDevice middleware - Searching for user', { username });
        const user = await User.findOne({ username });
        
        if (!user) {
            authLogger.warn('checkDevice middleware - User not found', { username });
            return res.status(400).json({ message: 'Invalid username' });
        }
        
        authLogger.info('checkDevice middleware - User found', { 
            username: user.username,
            userId: user._id 
        });

        const userDevices = await Device.find({ userId: user._id });
        authLogger.debug('checkDevice middleware - Found devices for user', { 
            userId: user._id,
            deviceCount: userDevices.length,
            devices: userDevices.map(d => d.deviceId)
        });

        // Check if the device is already registered
        const device = userDevices.find(d => d.deviceId === deviceId);

        if (device) {
            authLogger.info('checkDevice middleware - Device already registered, proceeding to login', {
                userId: user._id,
                deviceId
            });
            // Device is already registered, proceed to login
            next();
        } else {
            authLogger.debug('checkDevice middleware - Device not registered, checking limit', {
                userId: user._id,
                deviceId,
                currentDevices: userDevices.length,
                maxDevices: MAX_DEVICES
            });
            // Check if the user has reached the device limit
            if (userDevices.length >= MAX_DEVICES) {
                authLogger.warn('checkDevice middleware - Device limit reached', {
                    userId: user._id,
                    deviceId,
                    currentDevices: userDevices.length,
                    maxDevices: MAX_DEVICES
                });
                return res.status(400).json({
                    message: 'Your application is already installed and activated on another device. If you think this is an error, contact us.'
                });
            }
            authLogger.info('checkDevice middleware - Device limit OK, proceeding to login', {
                userId: user._id,
                deviceId,
                currentDevices: userDevices.length
            });
            // Device limit not reached, proceed to login (device will be registered in auth route)
            next();
        }

    } catch (error) {
        authLogger.error('checkDevice middleware - Error occurred', { 
            error: error.message,
            stack: error.stack,
            username,
            deviceId
        });
        res.status(500).json({ message: 'Internal Server Error' });
    }
};

export default checkDevice;
