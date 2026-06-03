# Multilingual Campaign Implementation Guide

## Overview
This guide documents the multilingual support system for The Give Hub campaigns. The implementation allows campaigns to have content in multiple languages with seamless switching and SEO-friendly static pages.

## ✅ Completed Components

### 1. Database Schema (Task #1)
- **Location**: `/docs/multilingual-schema.md`
- **Structure**:
  - Default language stored at root level (backwards compatible)
  - Translations stored in `translations` object keyed by language code
  - Partial translations supported with fallback to default language
  - Array-indexed translations for metrics and milestones

### 2. Language Switcher Web Component (Task #2)
- **Location**: `/components/language-switcher.js`
- **Features**:
  - Custom web component with Shadow DOM
  - Flag emojis for visual language identification
  - Responsive design (mobile shows only flags)
  - Emits `language-change` events
  - Automatically hides when only one language available
  - Supports 12 languages: en, es, fr, de, pt, zh, ar, it, ja, ko, ru, hi

### 3. Campaign Detail Page Integration (Task #3)
- **Location**: `/pages/campaign-detail.html`
- **Features**:
  - Language switcher integrated in header
  - URL parameter support (`?lang=es`)
  - Browser language auto-detection
  - Dynamic content switching without page reload
  - Translated content rendering with `getTranslatedContent()`
  - `switchLanguage()` function for programmatic switching
  - `initLanguageSupport()` initializes available languages

### 4. Campaign API Updates (Task #4)
- **Location**: `/lib/Campaign.php`
- **New Methods**:
  - `saveTranslation($id, $lang, $translationData)` - Save/update a translation
  - `getLanguages($id)` - Get available languages for a campaign
  - `deleteTranslation($id, $lang)` - Remove a translation
- **API Endpoints** (via auto-routing):
  - `POST /api/campaign/saveTranslation?id={id}&lang={lang}` - Save translation
  - `GET /api/campaign/getLanguages?id={id}` - Get available languages
  - `DELETE /api/campaign/deleteTranslation?id={id}&lang={lang}` - Delete translation

## 🚧 Remaining Tasks

### Task #5: Translation Editing UI
**Status**: Pending
**Description**: Add UI to campaign-detail.html edit mode for managing translations

**Requirements**:
1. In edit mode, show "Manage Translations" button
2. Modal/panel to:
   - List available translations
   - Add new language translation
   - Edit existing translation
   - Delete translation
3. Use EasyMDE editor for each language's description
4. Allow editing:
   - Title
   - Description
   - Location (region, country)
   - Impact metric names/units
   - Milestone titles/descriptions

**Implementation Approach**:
```javascript
// Add to campaign-detail.html
manageTranslations() {
  // Show modal with language list
  // For each language, show edit button
  // Open translation editor with pre-filled data
}

editTranslation(lang) {
  // Load translation data
  // Replace main content area with translation form
  // Use EasyMDE for description
  // Save back to translations object
}
```

### Task #6: Static Page Generation
**Status**: Pending
**Description**: Update `/scripts/generate-campaign-pages.php` to generate language-specific pages

**Requirements**:
1. Generate `campaign-slug.html` (default language)
2. Generate `campaign-slug.{lang}.html` for each translation
3. Add hreflang tags to all generated pages:
```html
<link rel="alternate" hreflang="en" href="campaign-slug.html" />
<link rel="alternate" hreflang="es" href="campaign-slug.es.html" />
<link rel="alternate" hreflang="fr" href="campaign-slug.fr.html" />
```
4. Include language switcher in all generated pages
5. Inject translated content into each page

**Implementation Approach**:
- Modify `regenerateStaticPage()` in Campaign.php
- Loop through available languages
- Generate separate HTML file for each
- Use campaign-detail.html as template
- Inject language-specific embedded data

## Usage Examples

### For Developers

#### 1. Adding a Translation Programmatically
```php
$campaign = new Campaign();
$translationData = [
    'title' => 'Título de la Campaña',
    'description' => 'Descripción en español...',
    'location' => [
        'region' => 'California',
        'country' => 'Estados Unidos'
    ],
    'impact' => [
        'metrics' => [
            ['name' => 'Personas Ayudadas', 'unit' => 'personas']
        ]
    ],
    'timeline' => [
        'milestones' => [
            ['title' => 'Lanzamiento', 'description' => 'Inicio del proyecto']
        ]
    ]
];

$result = $campaign->saveTranslation($campaignId, 'es', $translationData);
```

#### 2. Getting Translated Content in JavaScript
```javascript
// Switch to Spanish
app.switchLanguage('es');

// Get translated content
const translatedData = app.getTranslatedContent('es');
console.log(translatedData.title); // Spanish title
```

#### 3. Using the Language Switcher Component
```html
<!-- Add to your page -->
<language-switcher id="language-switcher"></language-switcher>

<script>
const switcher = document.getElementById('language-switcher');
switcher.setLanguages(['en', 'es', 'fr']);
switcher.setCurrentLanguage('en');

// Listen for changes
switcher.addEventListener('language-change', (e) => {
    console.log('Language changed to:', e.detail.language);
});
</script>
```

### For Campaign Managers

#### 1. Adding a Translation (Once UI is Complete)
1. Open your campaign
2. Click "Edit"
3. Click "Manage Translations" button
4. Click "Add Translation"
5. Select language
6. Fill in translated content
7. Click "Save"

#### 2. Switching Languages
- Look for the language switcher (flags) in the top-right corner
- Click on a flag to switch to that language
- URL will update to include `?lang=es`
- Share language-specific URLs with donors

## Database Examples

### Campaign with Spanish Translation
```json
{
  "_id": "507f1f77bcf86cd799439011",
  "defaultLanguage": "en",
  "title": "Help Build a School",
  "description": "We are building a school...",
  "location": {
    "region": "California",
    "country": "United States",
    "coordinates": { "latitude": 37.7749, "longitude": -122.4194 }
  },
  "translations": {
    "es": {
      "title": "Ayuda a Construir una Escuela",
      "description": "Estamos construyendo una escuela...",
      "location": {
        "region": "California",
        "country": "Estados Unidos"
      }
    }
  }
}
```

## Testing

### Test Cases
1. **Language Switching**
   - Load campaign
   - Click language switcher
   - Verify content changes
   - Verify URL updates

2. **URL Parameter**
   - Visit `campaign-detail.html?id=xxx&lang=es`
   - Verify Spanish content loads
   - Verify language switcher shows 'es' as active

3. **Browser Language Detection**
   - Set browser language to Spanish
   - Load campaign with Spanish translation
   - Verify Spanish content loads automatically

4. **Fallback**
   - Switch to language with incomplete translation
   - Verify missing fields fall back to default language

5. **API Endpoints**
   - Test `saveTranslation` endpoint
   - Test `getLanguages` endpoint
   - Test `deleteTranslation` endpoint

## SEO Considerations

### Benefits
- Separate URLs for each language (good for SEO)
- Proper hreflang tags (helps search engines)
- Language-specific metadata possible
- Better user experience = better engagement = better rankings

### Best Practices
1. Always include hreflang tags in static pages
2. Use canonical URLs to avoid duplicate content penalties
3. Include language in meta tags:
```html
<html lang="es">
<meta property="og:locale" content="es_ES">
```

## Future Enhancements

### Possible Improvements
1. **Machine Translation Integration**
   - Integrate Google Translate API
   - Offer automatic draft translations
   - Human review and editing

2. **Translation Status**
   - Track translation completion %
   - Mark fields as "needs review"
   - Show translation age/freshness

3. **Regional Variants**
   - Support regional language variants (es-ES vs es-MX)
   - Regional currency display
   - Regional date formats

4. **Translator Roles**
   - Allow volunteer translators
   - Translation review workflow
   - Translation credits

5. **Language Analytics**
   - Track which languages drive most donations
   - Language preference by region
   - A/B testing of translations

## Support

For questions or issues:
- Check this documentation first
- Review `/docs/multilingual-schema.md` for data structure
- Inspect browser console for language switching logs
- Check MongoDB for translation data

---

**Last Updated**: 2026-01-27
**Version**: 1.0
**Status**: Phase 1 Complete (Tasks 1-4), Phase 2 Pending (Tasks 5-6)
