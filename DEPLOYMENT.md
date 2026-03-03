# Railway Deployment Guide

This document explains how to deploy the DinoRace Laravel application to Railway.

## Prerequisites

- Railway account
- Git repository with the project code
- Railway CLI (optional)

## Environment Variables

Set these environment variables in your Railway project dashboard:

### Required Variables
- `APP_NAME=DinoRace`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` (generate with `php artisan key:generate --show`)
- `DB_CONNECTION=mysql`
- `DB_HOST` (Railway provides this)
- `DB_PORT=3306`
- `DB_DATABASE` (Railway provides this)
- `DB_USERNAME` (Railway provides this)
- `DB_PASSWORD` (Railway provides this)

### Optional Variables
- `REVERB_APP_ID`
- `REVERB_APP_KEY`
- `REVERB_APP_SECRET`
- `REVERB_HOST`
- `REVERB_PORT=443`
- `REVERB_SCHEME=https`

## Deployment Steps

### 1. Connect Repository
1. Go to Railway dashboard
2. Click "New Project"
3. Connect your Git repository
4. Railway will automatically detect the Dockerfile

### 2. Configure Database
1. Add a MySQL service to your Railway project
2. Railway will automatically provide database connection variables
3. The application will automatically run migrations on deployment

### 3. Set Environment Variables
1. Go to your project settings
2. Add the required environment variables listed above
3. Generate an APP_KEY: `php artisan key:generate --show`

### 4. Deploy
1. Railway will automatically build and deploy your application
2. The deployment process includes:
   - Building frontend assets
   - Installing PHP dependencies
   - Running database migrations
   - Optimizing Laravel for production

### 5. Verify Deployment
- Check the health endpoint: `https://your-app-url.railway.app/health`
- Visit the main application: `https://your-app-url.railway.app`

## Features

- **Multi-stage Docker build** for optimized container size
- **Automatic frontend asset compilation** with Vite
- **Database migrations** run automatically on deployment
- **Health check endpoint** for Railway monitoring
- **Production optimizations** (caching, optimization)
- **Real-time features** with Laravel Reverb support

## Troubleshooting

### Build Failures
- Check that all required files are committed to Git
- Verify the Dockerfile syntax
- Check Railway build logs

### Database Issues
- Ensure database service is added to Railway project
- Verify database connection variables
- Check migration logs in Railway console

### Runtime Issues
- Check application logs in Railway dashboard
- Verify environment variables are set correctly
- Test health endpoint connectivity

## Local Development

To test locally with Docker:

```bash
# Build the image
docker build -t dinorace .

# Run with environment variables
docker run -p 8080:8080 \
  -e APP_ENV=local \
  -e APP_DEBUG=true \
  -e DB_CONNECTION=sqlite \
  -e DB_DATABASE=/var/www/html/database/database.sqlite \
  dinorace
```

## Support

For Railway-specific issues, consult the [Railway documentation](https://docs.railway.app/).

For application-specific issues, check the application logs and ensure all environment variables are properly configured.
