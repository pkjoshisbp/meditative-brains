import fs from 'fs';
import path from 'path';
import winston from 'winston';

const LOG_DIR = path.join(process.cwd(), 'logs');

// Ensure log directory exists
if (!fs.existsSync(LOG_DIR)) {
    fs.mkdirSync(LOG_DIR, { recursive: true });
}

// Define log format
const logFormat = winston.format.combine(
    winston.format.timestamp({ format: 'YYYY-MM-DD HH:mm:ss.SSS' }),
    winston.format.errors({ stack: true }),
    winston.format.printf(({ timestamp, level, message, stack }) => {
        return `${timestamp} [${level.toUpperCase()}]: ${stack || message}`;
    })
);

// Create main logger
const mainLogger = winston.createLogger({
    level: 'debug',
    format: logFormat,
    transports: [
        // Write all logs to app.log
        new winston.transports.File({ 
            filename: path.join(LOG_DIR, 'app.log'),
            maxsize: 10485760, // 10MB
            maxFiles: 5,
        }),
        // Write error logs to error.log
        new winston.transports.File({ 
            filename: path.join(LOG_DIR, 'error.log'),
            level: 'error',
            maxsize: 5242880, // 5MB
            maxFiles: 3,
        }),
        // Also log to console
        new winston.transports.Console({
            format: winston.format.combine(
                winston.format.colorize(),
                winston.format.printf(({ timestamp, level, message }) => {
                    return `${timestamp} [${level}]: ${message}`;
                })
            )
        })
    ]
});

// Create auth-specific logger
export const authLogger = winston.createLogger({
    level: 'debug',
    format: logFormat,
    transports: [
        new winston.transports.File({ 
            filename: path.join(LOG_DIR, 'auth.log'),
            maxsize: 5242880, // 5MB
            maxFiles: 3,
        }),
        new winston.transports.Console({
            format: winston.format.combine(
                winston.format.colorize(),
                winston.format.printf(({ timestamp, level, message }) => {
                    return `${timestamp} [AUTH-${level}]: ${message}`;
                })
            )
        })
    ]
});

// Export the main logger as both default and named export for compatibility
export const logger = mainLogger;
export default mainLogger;
