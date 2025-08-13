
# Entegral Sync for Houzez (WordPress Plugin)

## Overview
**Author**: Levon Gravett  
**Developed For**: https://www.brokenpony.club  
**Version**: 1.5.0  
**Email**: levon@brokenpony.club

A comprehensive WordPress plugin that provides seamless integration between WordPress (Houzez theme) and the Entegral Sync API. This plugin enables automatic property listing synchronization, office and agent management, and advanced field mapping with robust error handling and debugging capabilities.

## Key Features

### 🔗 Core Entegral Integration
- **API Integration**: Secure connection to Entegral Sync API using credentials with timeout and error handling
- **Property Record Management**: Create, update, and sync property listings with comprehensive field mapping
- **Office & Agent Management**: Automatic office setup and agent data handling
- **Duplicate Detection**: Smart duplicate checking for listings
- **Comprehensive Logging**: Detailed debug logs for troubleshooting and monitoring

### 🏠 Houzez Theme Integration
- **Automatic Listing Sync**: Seamlessly sync all Houzez property listings to Entegral
- **Manual & Scheduled Sync**: One-click manual sync and optional scheduled (cron) sync
- **Admin Pages**: Custom admin pages for syncing, viewing, and debugging listings
- **Field Mapping**: Complete mapping of all property fields to Entegral API equivalents, including:
  - Property details (type, status, price, size, etc.)
  - Address and location
  - Features and amenities
  - Photos and media
  - Agent and office details
- **Error Recovery**: Robust error handling with detailed logging and recovery mechanisms

### 🛠️ Advanced Features
- **Data Type Handling**: Ensures all required fields (especially integer fields) are set and not left blank
- **Admin Visualization**: Clear admin interface showing all listings, logs, and sync status
- **Debug Output & Logging**: Built-in debug output and logging for troubleshooting sync and API logic
- **No ACF Required**: All required fields are managed by the plugin and Houzez theme

## Installation & Setup

### Requirements
- WordPress 5.0 or higher
- PHP 7.2 or higher
- Houzez theme (for property data)
- Valid Entegral Sync API credentials
- cURL extension enabled

### Installation Steps
1. **Upload Plugin Files**
   - Upload the plugin folder to `/wp-content/plugins/entegral-wordpress-api/`
2. **Activate Plugin**
   - Activate through the 'Plugins' screen in WordPress
3. **Configure Settings**
   - Navigate to 'Entegral Sync' in the admin menu

### Configuration
#### API Settings
- **API URL**: Enter your Entegral Sync API endpoint
- **API Key & SourceID**: Provide your Entegral API authentication key and SourceID
- **Test Connection**: Use the built-in test to verify connectivity and credentials

#### Office & Agent Setup
- Enter your office name, contact details, and other required information in the plugin settings
- The plugin will automatically create or update your office in Entegral if needed
- Agent details are managed automatically from Houzez agent data

#### Sync Listings
- Use the "Sync All Listings" page to manually sync all Houzez listings to Entegral
- Optionally, enable scheduled syncs (via WordPress cron) for automatic updates

## Usage Guide

### Manual Sync
- Go to "Entegral Sync > Sync All Listings" and click the button to sync
- After syncing, a list of synced listings will be displayed (ID, type, price)

### View Listings
- Use the "View All Listings" and "View Listing Details" pages to inspect your data

### Logs
- Check the "View Listing Logs" page for detailed sync/debug information

## Property Record Management

### Supported Fields
- **Property Information**: Type, status, price, size, description, features
- **Location Details**: Country, province, town, suburb, address
- **Agent & Office**: Agent name, contact, office ID
- **Photos & Media**: All images and media attached to the listing
- **Custom Fields**: Any additional fields mapped from Houzez to Entegral

### Field Mapping Details
// Houzez Field → Entegral API Field
- `propertyType` → `propertyType`
- `propertyStatus` → `propertyStatus`
- `price` → `price`
- `beds` → `beds`
- `baths` → `baths`
- ...and more (see code for full mapping)

## Error Handling & Debugging

### Comprehensive Logging & Debugging
- **Sync Debug Log**: Step-by-step sync process logging
- **API Log**: Detailed API interaction logs
- **WordPress Debug Log**: Integration with WordPress debugging
- **Admin Notifications**: Real-time error reporting in admin interface

### Debug Features
- Clear log files before testing
- Detailed API response logging for all endpoints
- Sync process tracking
- Error recovery mechanisms
- Debug output panels in admin for troubleshooting

## Support & Documentation

### Debugging
Enable WordPress debugging and check the following log files:
- `/wp-content/debug.log` - WordPress debug log
- `/wp-content/plugins/entegral-wordpress-api/debug.log` - Plugin debug log

### Common Issues
- **API Connection Failures**: Verify API credentials and network connectivity
- **Field Mapping Issues**: Check field naming conventions in Houzez
- **Duplicate Records**: Review duplicate detection logic
- **Sync Failures**: Ensure all required fields are set and not blank

### Testing & Monitoring
- **Connection Testing**: Use built-in API connection testing tools
- **Sync Testing**: Test manual and scheduled syncs
- **Debug Logs**: Monitor debug logs for detailed process tracking
- **Error Monitoring**: Check admin notifications for real-time error reporting

### Support
For technical support or custom development:

🌐 https://www.brokenpony.club  
📧 Levon Gravett: levon@brokenpony.club

Documentation: Refer to inline code comments and debug logs

## License
This plugin is proprietary software developed by Levon Gravett for Broken Pony Club. All rights reserved.
