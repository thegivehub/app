# Web Component Navigation Guide

This guide explains how to use the new web component-based navigation system in the admin dashboard.

## Overview

We've converted the navigation system into a web component called `<admin-navigation>`. This approach offers several advantages:

1. **Encapsulation**: The navigation logic and styles are isolated from the rest of the application
2. **Reusability**: The navigation can be reused across different parts of your application
3. **Maintainability**: Updates to navigation are contained within the component
4. **Shadow DOM**: Prevents style conflicts with other parts of your application

## Setup

### Files Structure

1. `admin-navigation.js` - The web component definition
2. `admin-controller.html` - The main controller page using the web component
3. `nav/nav.json` - JSON file containing the navigation structure (same as before)

### Installation

1. Add the web component script to your page:

```html
<script src="admin-navigation.js"></script>
```

2. Use the component in your HTML:

```html
<admin-navigation 
    id="admin-nav" 
    data-nav-url="nav/nav.json" 
    data-default-page="campaigns.html">
</admin-navigation>
```

## Web Component Attributes

The `<admin-navigation>` component accepts these attributes:

- `data-nav-url`: Path to the JSON file containing navigation structure (default: "nav/nav.json")
- `data-default-page`: Page to load when no specific page is requested (default: "campaigns.html")

## Events

The component emits several events that you can listen for:

1. **navigation-loaded**: Fired when the navigation data is successfully loaded from JSON
   ```javascript
   document.getElementById('admin-nav').addEventListener('navigation-loaded', (e) => {
       console.log('Navigation data loaded:', e.detail.navData);
   });
   ```

2. **navigation-rendered**: Fired when the navigation is rendered in the DOM
   ```javascript
   document.getElementById('admin-nav').addEventListener('navigation-rendered', () => {
       console.log('Navigation has been rendered');
   });
   ```

3. **navigation-click**: Fired when a navigation item is clicked
   ```javascript
   document.getElementById('admin-nav').addEventListener('navigation-click', (e) => {
       console.log('Navigation item clicked:', e.detail.page);
       // Load the page in an iframe or using another method
       document.getElementById('content-frame').src = e.detail.page;
   });
   ```

## URL Navigation Features

The navigation component supports the same URL navigation as before:

1. **Query Parameters**: Use `?page=pagename.html` in the URL
2. **Hash Navigation**: Use `#pagename` in the URL

## Customization

You can customize the component by extending it or by overriding its internal styles. The component uses Shadow DOM, so you'll need to use CSS custom properties or the `::part()` selector to style elements inside it.

## Example Integration

```javascript
// Listen for navigation click events
document.getElementById('admin-nav').addEventListener('navigation-click', (e) => {
    // Load the page in an iframe
    document.getElementById('content-frame').src = e.detail.page;
    
    // Update browser URL
    const url = new URL(window.location.href);
    url.searchParams.set('page', e.detail.page);
    url.hash = e.detail.page.replace('.html', '');
    window.history.pushState({}, '', url);
});

// Use navigation data for other purposes
document.getElementById('admin-nav').addEventListener('navigation-loaded', (e) => {
    // Render top navigation
    renderTopNav(e.detail.navData.topmenu);
});
```

## Advanced Usage

### Programmatically Activating Navigation Items

You can programmatically set the active navigation item by dispatching a custom event:

```javascript
const event = new CustomEvent('set-active-page', { 
  detail: { page: 'users.html' } 
});
document.getElementById('admin-nav').dispatchEvent(event);
```

### Extending the Component

You can extend the web component to add custom functionality:

```javascript
class CustomNavigation extends AdminNavigation {
  constructor() {
    super();
    // Add custom initialization
  }
  
  // Override or add methods
}

customElements.define('custom-navigation', CustomNavigation);
```
