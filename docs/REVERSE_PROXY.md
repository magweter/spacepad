# Using Spacepad with Nginx and Apache

This guide explains how to configure Spacepad behind Nginx or Apache reverse proxies. By default, Spacepad runs on port `8080` inside the container and can be accessed directly, but using a reverse proxy provides benefits like SSL termination, better performance, and easier domain management.

## Prerequisites

- Spacepad container running (using `docker compose up -d` or `docker compose -f docker-compose.yml up -d`)
- Nginx or Apache installed on your host system
- Domain name pointing to your server (for SSL certificates)
- Basic understanding of reverse proxy configuration

## General Configuration Notes

- **Container Port**: Spacepad listens on port `8080` inside the container
- **Proxy Protocol**: The application trusts all proxies, so it will correctly handle forwarded headers
- **Health Check**: The application exposes a health endpoint at `/health`
- **Static Files**: Laravel handles static assets through the application, so all requests should be proxied

## Nginx Configuration

### Basic HTTP Configuration

Create or edit your Nginx configuration file (typically `/etc/nginx/sites-available/spacepad`):

```nginx
server {
    listen 80;
    server_name your-domain.com;

    # Increase body size limit for file uploads
    client_max_body_size 100M;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        
        # Headers for proper proxying
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        
        # WebSocket support (if needed)
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        
        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
        
        # Buffering
        proxy_buffering off;
        proxy_request_buffering off;
    }
}
```

### HTTPS Configuration with Let's Encrypt

For production use, you should enable HTTPS. Here's a complete configuration using Let's Encrypt:

```nginx
# HTTP server - redirect to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name your-domain.com;

    # Let's Encrypt challenge
    location /.well-known/acme-challenge/ {
        root /var/www/html;
    }

    # Redirect all other traffic to HTTPS
    location / {
        return 301 https://$server_name$request_uri;
    }
}

# HTTPS server
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name your-domain.com;

    # SSL certificates (adjust paths based on your certbot setup)
    ssl_certificate /etc/letsencrypt/live/your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.com/privkey.pem;
    
    # SSL configuration
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Increase body size limit for file uploads
    client_max_body_size 100M;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        
        # Headers for proper proxying
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
        
        # WebSocket support (if needed)
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        
        # Timeouts
        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
        
        # Buffering
        proxy_buffering off;
        proxy_request_buffering off;
    }

    # Health check endpoint
    location /health {
        proxy_pass http://127.0.0.1:8080/health;
        access_log off;
    }
}
```

### Enabling the Configuration

1. Create a symbolic link to enable the site:
   ```bash
   sudo ln -s /etc/nginx/sites-available/spacepad /etc/nginx/sites-enabled/
   ```

2. Test the configuration:
   ```bash
   sudo nginx -t
   ```

3. Reload Nginx:
   ```bash
   sudo systemctl reload nginx
   ```

### Obtaining SSL Certificates with Certbot

If you haven't already obtained SSL certificates:

```bash
# Install certbot
sudo apt-get update
sudo apt-get install certbot python3-certbot-nginx

# Obtain certificate (Nginx will automatically configure SSL)
sudo certbot --nginx -d your-domain.com

# Test automatic renewal
sudo certbot renew --dry-run
```

## Apache Configuration

### Enable Required Modules

First, enable the necessary Apache modules:

```bash
sudo a2enmod proxy
sudo a2enmod proxy_http
sudo a2enmod headers
sudo a2enmod ssl
sudo a2enmod rewrite
```

### Basic HTTP Configuration

Create or edit your Apache virtual host configuration (typically `/etc/apache2/sites-available/spacepad.conf`):

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    
    # Increase body size limit for file uploads
    LimitRequestBody 104857600

    ProxyPreserveHost On
    ProxyRequests Off
    
    # Proxy all requests to Spacepad container
    ProxyPass / http://127.0.0.1:8080/
    ProxyPassReverse / http://127.0.0.1:8080/
    
    # Headers for proper proxying
    RequestHeader set X-Forwarded-Proto "http"
    RequestHeader set X-Forwarded-Port "80"
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/spacepad_error.log
    CustomLog ${APACHE_LOG_DIR}/spacepad_access.log combined
</VirtualHost>
```

### HTTPS Configuration with Let's Encrypt

For production use with HTTPS:

```apache
# HTTP server - redirect to HTTPS
<VirtualHost *:80>
    ServerName your-domain.com
    
    # Let's Encrypt challenge
    <Location /.well-known/acme-challenge/>
        ProxyPass !
    </Location>
    Alias /.well-known/acme-challenge/ /var/www/html/.well-known/acme-challenge/
    
    # Redirect all other traffic to HTTPS
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</VirtualHost>

# HTTPS server
<VirtualHost *:443>
    ServerName your-domain.com
    
    # SSL configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/your-domain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/your-domain.com/privkey.pem
    
    # SSL protocol and cipher configuration
    SSLProtocol all -SSLv2 -SSLv3
    SSLCipherSuite HIGH:!aNULL:!MD5
    SSLHonorCipherOrder on

    # Security headers
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"

    # Increase body size limit for file uploads
    LimitRequestBody 104857600

    ProxyPreserveHost On
    ProxyRequests Off
    
    # Proxy all requests to Spacepad container
    ProxyPass / http://127.0.0.1:8080/
    ProxyPassReverse / http://127.0.0.1:8080/
    
    # Headers for proper proxying
    RequestHeader set X-Forwarded-Proto "https"
    RequestHeader set X-Forwarded-Port "443"
    
    # WebSocket support (if needed)
    RewriteEngine on
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteRule ^/?(.*) "ws://127.0.0.1:8080/$1" [P,L]
    
    # Logging
    ErrorLog ${APACHE_LOG_DIR}/spacepad_ssl_error.log
    CustomLog ${APACHE_LOG_DIR}/spacepad_ssl_access.log combined
</VirtualHost>
```

### Enabling the Configuration

1. Enable the site:
   ```bash
   sudo a2ensite spacepad.conf
   ```

2. Disable the default site (if needed):
   ```bash
   sudo a2dissite 000-default.conf
   ```

3. Test the configuration:
   ```bash
   sudo apache2ctl configtest
   ```

4. Reload Apache:
   ```bash
   sudo systemctl reload apache2
   ```

### Obtaining SSL Certificates with Certbot

If you haven't already obtained SSL certificates:

```bash
# Install certbot
sudo apt-get update
sudo apt-get install certbot python3-certbot-apache

# Obtain certificate (Apache will automatically configure SSL)
sudo certbot --apache -d your-domain.com

# Test automatic renewal
sudo certbot renew --dry-run
```

## Docker Compose Configuration

When using a reverse proxy, you typically don't need to expose port 8080 to the host. However, if your reverse proxy is running on the same host (not in Docker), you'll need to keep the port mapping.

### Option 1: Reverse Proxy on Host (Recommended)

Keep the port mapping in `docker-compose.yml`:

```yaml
services:
  app:
    image: ghcr.io/magweter/spacepad:latest
    restart: unless-stopped
    platform: linux/amd64
    ports:
      - "127.0.0.1:8080:8080"  # Only bind to localhost
    volumes:
      - storage_data:/var/www/html/storage
      - .env:/var/www/html/.env:ro
    # ... rest of configuration
```

Binding to `127.0.0.1:8080` ensures the port is only accessible from the localhost, which is more secure.

### Option 2: Reverse Proxy in Docker Network

If your reverse proxy is also running in Docker, you can use a shared network:

```yaml
name: spacepad
services:
  app:
    image: ghcr.io/magweter/spacepad:latest
    restart: unless-stopped
    platform: linux/amd64
    networks:
      - proxy_network
    volumes:
      - storage_data:/var/www/html/storage
      - .env:/var/www/html/.env:ro
    # ... rest of configuration

networks:
  proxy_network:
    external: true
```

Then configure your reverse proxy to use the service name `app` instead of `127.0.0.1:8080`.

## Troubleshooting

### Connection Refused

- **Check container is running**: `docker compose ps`
- **Verify port mapping**: `docker compose port app 8080`
- **Check firewall**: Ensure port 80/443 are open, but 8080 can be restricted to localhost

### 502 Bad Gateway

- **Check container logs**: `docker compose logs app`
- **Verify proxy_pass URL**: Ensure it matches your container's exposed port
- **Check network connectivity**: Test with `curl http://127.0.0.1:8080/health`

### SSL Certificate Issues

- **Verify certificate paths**: Ensure paths in configuration match actual certificate locations
- **Check certificate expiration**: `sudo certbot certificates`
- **Test renewal**: `sudo certbot renew --dry-run`

### Headers Not Working

- **Verify proxy headers**: Ensure all `X-Forwarded-*` headers are set correctly
- **Check Laravel trust proxies**: The application trusts all proxies by default, but verify your `.env` doesn't override this

### Performance Issues

- **Enable caching**: Consider adding caching headers for static assets
- **Adjust timeouts**: Increase proxy timeouts if requests are timing out
- **Check container resources**: Ensure Docker has adequate CPU and memory

## Additional Security Considerations

1. **Restrict container port**: Bind port 8080 only to `127.0.0.1` instead of `0.0.0.0`
2. **Rate limiting**: Consider adding rate limiting in your reverse proxy configuration
3. **IP whitelisting**: If needed, restrict access by IP in your reverse proxy
4. **Fail2ban**: Consider setting up Fail2ban to protect against brute force attacks
5. **Regular updates**: Keep your reverse proxy and SSL certificates up to date

## Testing Your Configuration

After configuring your reverse proxy:

1. **Test HTTP redirect** (if using HTTPS):
   ```bash
   curl -I http://your-domain.com
   ```

2. **Test HTTPS connection**:
   ```bash
   curl -I https://your-domain.com
   ```

3. **Test health endpoint**:
   ```bash
   curl https://your-domain.com/health
   ```

4. **Verify headers**:
   ```bash
   curl -I https://your-domain.com | grep -i "strict-transport"
   ```

## Next Steps

Once your reverse proxy is configured:

1. Update your `.env` file with the correct `APP_URL`:
   ```env
   APP_URL=https://your-domain.com
   ```

2. Restart your Spacepad containers:
   ```bash
   docker compose restart
   ```

3. Test the application in your browser and verify all functionality works correctly

4. Set up monitoring and logging for your reverse proxy to track usage and troubleshoot issues

