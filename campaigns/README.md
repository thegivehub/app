# Campaign Static Pages

This directory contains pre-generated static HTML pages for published campaigns with SEO-friendly URLs.

## Features

✅ **SEO-Friendly URLs**: `/campaigns/clean-water-kenya` instead of `/pages/campaign-detail.html?id=123abc`
✅ **Fast Loading**: Pre-rendered HTML with embedded JSON data
✅ **Search Engine Crawlable**: Static HTML pages are fully crawlable
✅ **Social Media Ready**: Includes Open Graph and Twitter Card meta tags
✅ **Auto-Generated**: Pages regenerate automatically when campaigns are created/updated

## URL Structure

- **Clean URL**: `https://app.thegivehub.com/campaigns/campaign-slug`
- **With Extension**: `https://app.thegivehub.com/campaigns/campaign-slug.html`

Both URLs work thanks to `.htaccess` rewrite rules.

## How It Works

### 1. Static Page Generation

Pages are generated from the `campaign-detail.html` template with:
- Campaign data embedded as JSON in a `<script id="campaign-data">` tag
- SEO meta tags (title, description, Open Graph, Twitter Card)
- Campaign-specific canonical URL

### 2. Automatic Regeneration

Static pages are automatically regenerated when:
- A campaign is created with `status: 'published'`
- A campaign is updated to `status: 'published'`

The regeneration happens in the background via `Campaign.php::regenerateStaticPage()`.

### 3. Hybrid Rendering

The `campaign-detail.html` page supports both modes:
- **Static Mode**: Reads embedded JSON data (fast, no API call)
- **Dynamic Mode**: Fetches from API with `?id=` parameter (fallback)

## Manual Generation

### Generate All Published Campaigns

```bash
php scripts/generate-campaign-pages.php
```

### Generate Specific Campaign

```bash
php scripts/generate-campaign-pages.php <campaign_id>
```

Example:
```bash
php scripts/generate-campaign-pages.php 696146b67b36e74a5104e813
```

## File Naming

Filenames are generated from campaign titles:
- Lowercase
- Spaces replaced with hyphens
- Special characters removed
- Max 100 characters
- Example: `"Clean Water for Kenya"` → `clean-water-for-kenya.html`

## Benefits

### For Users
- ⚡ Faster page loads (no API call needed)
- 🔗 Easy-to-share URLs
- 📱 Better mobile experience

### For SEO
- 🔍 Search engines can crawl and index content
- 📊 Better Google rankings
- 🎯 Rich social media previews
- 📈 Improved click-through rates

### For Sharing
- 👥 Clean URLs for social media
- 🖼️ Proper Open Graph images
- 📝 Accurate preview cards
- 💬 Professional appearance

## Maintenance

### Cron Job (Optional)

For extra safety, you can set up a cron job to regenerate all pages nightly:

```bash
# Daily at 3 AM
0 3 * * * cd /home/cdr/domains/thegivehub.com/app && php scripts/generate-campaign-pages.php > /dev/null 2>&1
```

### Cleanup Old Pages

If campaigns are deleted or unpublished, their static pages remain. You can manually delete them:

```bash
# Remove specific campaign
rm /home/cdr/domains/thegivehub.com/app/campaigns/old-campaign.html

# Clean all and regenerate
rm /home/cdr/domains/thegivehub.com/app/campaigns/*.html
php scripts/generate-campaign-pages.php
```

## Technical Details

### Files Modified
- `scripts/generate-campaign-pages.php` - Generator script
- `pages/campaign-detail.html` - Updated to support embedded data
- `lib/Campaign.php` - Added auto-regeneration hooks
- `.htaccess` - Added URL rewrite rules

### Generated Page Structure
```html
<head>
    <!-- SEO Meta Tags -->
    <meta name="description" content="...">
    <link rel="canonical" href="...">
    <meta property="og:title" content="...">
    <!-- etc. -->

    <!-- Embedded Campaign Data -->
    <script id="campaign-data" type="application/json">
        { "title": "...", "description": "...", ... }
    </script>
</head>
```

## Troubleshooting

### Pages Not Generating
- Check script is executable: `chmod +x scripts/generate-campaign-pages.php`
- Check directory permissions: `chmod 755 campaigns/`
- Check error logs: `tail -f logs/*.log`

### Clean URLs Not Working
- Check `.htaccess` is being read: `apache2ctl -M | grep rewrite`
- Check `mod_rewrite` is enabled
- Clear browser cache

### Pages Not Updating
- Verify campaign is `published` status
- Check Campaign.php has `regenerateStaticPage()` method
- Run manual generation to test

## Future Enhancements

Possible improvements:
- 🗺️ Sitemap generation
- 📡 RSS feed for campaigns
- 🔄 Incremental regeneration (only changed campaigns)
- 📊 Analytics tracking in static pages
- 🌐 Multi-language support
