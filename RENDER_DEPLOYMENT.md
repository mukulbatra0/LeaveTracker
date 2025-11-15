# Deploying ELMS to Render.com

This guide will help you deploy your Employee Leave Management System (ELMS) to Render.com.

## Prerequisites

1. A Render.com account (free tier available)
2. Your code pushed to a Git repository (GitHub, GitLab, or Bitbucket)

## Deployment Steps

### Step 1: Prepare Your Repository

1. Ensure all the deployment files are in your repository:
   - `render.yaml` - Render service configuration
   - `build.sh` - Build script
   - `setup-render-db.php` - Database initialization
   - `start.php` - Server startup script

### Step 2: Create Database Service

1. Log into your Render dashboard
2. Click "New +" and select "PostgreSQL" or "MySQL"
3. Configure the database:
   - **Name**: `elms-db`
   - **Database Name**: `elms_db`
   - **User**: `elms_user`
   - **Region**: Choose closest to your users
   - **Plan**: Free tier is sufficient for testing

### Step 3: Create Web Service

1. In Render dashboard, click "New +" and select "Web Service"
2. Connect your Git repository
3. Configure the service:
   - **Name**: `elms-app`
   - **Environment**: `PHP`
   - **Build Command**: `./build.sh`
   - **Start Command**: `php start.php`
   - **Plan**: Free tier available

### Step 4: Environment Variables

The following environment variables will be automatically set by Render when you link the database:
- `DB_HOST`
- `DB_NAME` 
- `DB_USER`
- `DB_PASS`

Additional variables you can set:
- `APP_ENV=production`
- `DEBUG_MODE=false`
- `SESSION_TIMEOUT=3600`

### Step 5: Deploy

1. Click "Create Web Service"
2. Render will automatically:
   - Clone your repository
   - Run the build script
   - Install dependencies
   - Set up the database
   - Start your application

### Step 6: Access Your Application

Once deployed, you can access your ELMS at the provided Render URL.

**Default Login Credentials:**
- **Admin**: admin@college.edu / password123
- **Director**: director@college.edu / password123  
- **Head of Department**: hod@college.edu / password123
- **Staff**: staff@college.edu / password123

⚠️ **Important**: Change these default passwords immediately after first login!

## Configuration Options

### Custom Domain
You can add a custom domain in the Render dashboard under your service settings.

### Environment Variables
Add any additional configuration through the Render dashboard:
- Email settings (SMTP)
- File upload limits
- Security keys

### Scaling
Render automatically handles scaling. You can upgrade to paid plans for:
- More resources
- Custom domains
- Better performance
- SSL certificates

## Troubleshooting

### Build Failures
- Check the build logs in Render dashboard
- Ensure all dependencies are in `composer.json`
- Verify file permissions

### Database Connection Issues
- Verify database service is running
- Check environment variables are set correctly
- Review database logs

### Application Errors
- Enable debug mode temporarily: `DEBUG_MODE=true`
- Check application logs in Render dashboard
- Verify file upload directory permissions

## File Structure for Render

```
your-project/
├── render.yaml              # Render configuration
├── build.sh                 # Build script
├── start.php               # Startup script  
├── setup-render-db.php     # Database setup
├── composer.json           # PHP dependencies
├── .env.example           # Environment template
├── config/
│   └── db.php             # Database connection
├── uploads/               # File uploads (auto-created)
└── ... (rest of your ELMS files)
```

## Security Considerations

1. **Change Default Passwords**: Update all default user passwords
2. **Environment Variables**: Never commit sensitive data to Git
3. **File Permissions**: Render handles most permissions automatically
4. **HTTPS**: Render provides free SSL certificates
5. **Database**: Use strong database passwords

## Monitoring

Render provides:
- Application logs
- Performance metrics  
- Uptime monitoring
- Error tracking

Access these through your Render dashboard.

## Support

- **Render Documentation**: https://render.com/docs
- **ELMS Issues**: Check your repository issues
- **Community**: Render community forums

## Cost Estimation

**Free Tier Includes:**
- 750 hours/month web service
- PostgreSQL database with 1GB storage
- 100GB bandwidth
- Custom domains on paid plans

**Paid Plans Start At:**
- $7/month for web services
- $7/month for databases
- Additional features and resources

Your ELMS should run comfortably on the free tier for development and small teams.