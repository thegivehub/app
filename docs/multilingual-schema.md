# Multilingual Campaign Schema

## Overview
Campaigns will support multiple languages with a default language and optional translations.

## Database Schema

```javascript
{
  "_id": ObjectId,
  "defaultLanguage": "en",  // ISO 639-1 language code

  // Default language content (backwards compatible)
  "title": "Campaign Title",
  "description": "Campaign description in default language...",
  "location": {
    "region": "California",
    "country": "United States",
    "coordinates": { latitude: 37.7749, longitude: -122.4194 }
  },

  // Other non-translatable fields
  "funding": { ... },
  "creatorId": "...",
  "status": "active",
  "createdAt": "...",

  // Impact metrics with default language
  "impact": {
    "metrics": [
      {
        "name": "People Helped",
        "baseline": 0,
        "current": 150,
        "target": 1000,
        "unit": "people"
      }
    ]
  },

  // Timeline with default language
  "timeline": {
    "milestones": [
      {
        "title": "Project Launch",
        "description": "Initial project setup and launch",
        "scheduledDate": "2024-01-01",
        "status": "completed"
      }
    ]
  },

  // Translations object containing all alternate languages
  "translations": {
    "es": {  // Spanish translation
      "title": "Título de la Campaña",
      "description": "Descripción de la campaña en español...",
      "location": {
        "region": "California",
        "country": "Estados Unidos"
      },
      "impact": {
        "metrics": [
          {
            "name": "Personas Ayudadas",
            "unit": "personas"
          }
        ]
      },
      "timeline": {
        "milestones": [
          {
            "title": "Lanzamiento del Proyecto",
            "description": "Configuración inicial y lanzamiento del proyecto"
          }
        ]
      }
    },
    "fr": {  // French translation
      "title": "Titre de la Campagne",
      "description": "Description de la campagne en français...",
      // ... more translations
    }
  }
}
```

## Key Design Decisions

1. **Default Language First**: The root level contains the default language content for backwards compatibility
2. **Translations Object**: All alternate languages stored in a `translations` object keyed by language code
3. **Partial Translations**: Not all fields need to be translated (coordinates stay the same)
4. **Array Matching**: Metrics and milestones translations match by index with the default language arrays
5. **Fallback**: If a translation is missing, fall back to the default language

## Supported Languages (Initial)
- en - English (default)
- es - Spanish
- fr - French
- de - German
- pt - Portuguese
- zh - Chinese
- ar - Arabic

## API Endpoints

### Get Campaign with Language
```
GET /api/campaign?id={id}&lang=es
```

### Save Translation
```
PUT /api/campaign/translation?id={id}&lang=es
Body: { translation data }
```

### Get Available Languages
```
GET /api/campaign/languages?id={id}
Returns: ["en", "es", "fr"]
```

## Static Page Generation

Generate separate HTML files for each language:
- `campaign-slug.html` (default language)
- `campaign-slug.es.html` (Spanish)
- `campaign-slug.fr.html` (French)

Each page includes hreflang tags for SEO:
```html
<link rel="alternate" hreflang="en" href="campaign-slug.html" />
<link rel="alternate" hreflang="es" href="campaign-slug.es.html" />
<link rel="alternate" hreflang="fr" href="campaign-slug.fr.html" />
```
