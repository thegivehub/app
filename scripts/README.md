# Image Migration Tools

This directory contains tools to migrate base64-encoded images from the MongoDB database to the filesystem.

## Purpose

The migration addresses several issues with storing base64 images directly in the database:

1. **Performance**: Database queries are slower with large binary data
2. **Page Load Times**: Base64 encoded images increase page load times
3. **No Caching**: Browsers can't cache images stored in the database
4. **Database Size**: Database size is unnecessarily large with binary data

The migration moves images to the proper filesystem locations:
- Campaign images: `/uploads/campaigns/<CAMPAIGN_ID>/<UUID>.jpg`
- Profile images: `/uploads/profiles/<UUID>.jpg`
- Documents: `/uploads/documents/<UUID>.jpg`
- Selfies: `/uploads/selfies/<UUID>.jpg`

## Migration Scripts

### migrate_images.php

This PHP script handles the actual migration by:

1. Finding all documents in the database with base64 encoded images
2. Converting the base64 data to image files on the filesystem
3. Updating the database records to point to the file URLs
4. Logging statistics and errors

Command line options:
```
php migrate_images.php [--dry-run] [--debug]
```

Options:
- `--dry-run`: Test mode - validate but don't save files or update database
- `--debug`: Enable additional debug output

### migrate_images.sh

A shell script wrapper that:

1. Sets up the environment
2. Runs the PHP migration script
3. Logs all output to a timestamped log file

Command line options:
```
bash migrate_images.sh [--dry-run] [--debug] [--help]
```

Options:
- `--dry-run`: Test mode - validate but don't save files or update database
- `--debug`: Enable additional debug output
- `--help`: Display usage information

## Usage

To run the migration:

```bash
cd /path/to/app

# Full migration
bash scripts/migrate_images.sh

# Test run without making changes
bash scripts/migrate_images.sh --dry-run

# Debug mode with more verbose output
bash scripts/migrate_images.sh --debug

# Combine options
bash scripts/migrate_images.sh --dry-run --debug
```

## Before Migration

Make sure to:

1. **Backup your database**: This is a one-way migration
2. **Test in development first**: Run with `--dry-run` to check for potential issues
3. **Check disk space**: Ensure you have enough space for all images

## After Migration

After successful migration:

1. Check the migration logs for any errors
2. Verify sample images to ensure they display correctly
3. Monitor site performance and load times

## Troubleshooting

If you encounter issues with "Invalid base64 image format" errors:

1. Run with `--debug` option to see more details: `bash scripts/migrate_images.sh --debug`
2. Check if your images actually use the standard base64 format: `data:image/jpeg;base64,/9j/...`
3. If your database contains URLs instead of base64 data, the script will now automatically detect and keep them
4. For invalid data, you can manually update records after migration 
## Task Management

The `update_unfinished_tasks.sh` script helps reconcile the project tracker with the repository history. It fetches tasks from the remote API, searches commit messages for matching task names, and updates each task's notes with the commit hash. If an unfinished task has a matching commit, it is automatically marked as completed.

Run the script from the repository root:

```bash
bash scripts/update_unfinished_tasks.sh
```

Ensure you have network access to `project.thegivehub.com` and appropriate permissions before running.
