# Resume Analysis API Documentation

## Overview
This API uses Google Gemini AI to analyze resumes and provide intelligent feedback, scoring, and recommendations.

## API Endpoint
```
POST /api/resume_analysis.php
```

## Authentication
Currently uses a hardcoded Google Gemini API key. For production, implement proper API key management.

**API Key:** `AIzaSyAydm36D5YafWRCCpxYuL579P2R8CdkFbA`

## Request Format

### Content-Type
`application/json`

### Request Body
```json
{
  "resume_text": "Your resume content here...",
  "job_description": "Optional job description for matching analysis"
}
```

### Parameters
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `resume_text` | string | Yes | The full text content of the resume |
| `job_description` | string | No | Job description to match against (optional) |

## Response Format

### Success Response (200 OK)
```json
{
  "success": true,
  "analysis": {
    "score": 85,
    "strengths": [
      "Clear and professional formatting",
      "Strong technical skills section",
      "Good project descriptions"
    ],
    "weaknesses": [
      "Missing quantifiable achievements",
      "No soft skills mentioned"
    ],
    "suggestions": [
      "Add specific metrics to achievements",
      "Include leadership experience"
    ],
    "recommendation": "Approve with minor improvements needed"
  },
  "timestamp": "2025-10-10 10:50:00"
}
```

### Error Response (400/500)
```json
{
  "error": "Error message here",
  "usage": {
    "method": "POST",
    "content_type": "application/json",
    "body": {
      "resume_text": "Your resume content here",
      "job_description": "Optional job description"
    }
  }
}
```

## Analysis Fields

### score
- **Type:** Integer (0-100)
- **Description:** Overall quality score of the resume

### strengths
- **Type:** Array of strings
- **Description:** 3-5 key strengths identified in the resume

### weaknesses
- **Type:** Array of strings
- **Description:** 3-5 areas that need improvement

### suggestions
- **Type:** Array of strings
- **Description:** 3-5 specific, actionable recommendations

### recommendation
- **Type:** String
- **Description:** Overall recommendation (Approve/Reject/Needs Improvement)

## Usage Examples

### cURL Example
```bash
curl -X POST http://localhost:8000/api/resume_analysis.php \
  -H "Content-Type: application/json" \
  -d '{
    "resume_text": "John Doe\nSoftware Engineer\n\nExperience:\n- 3 years at ABC Corp\n- Developed web applications\n\nSkills:\nJavaScript, Python, SQL"
  }'
```

### JavaScript (Fetch API)
```javascript
const response = await fetch('http://localhost:8000/api/resume_analysis.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    resume_text: 'Your resume content here...',
    job_description: 'Optional job description'
  })
});

const data = await response.json();
console.log(data);
```

### PHP Example
```php
$data = [
    'resume_text' => 'Your resume content here...',
    'job_description' => 'Optional job description'
];

$ch = curl_init('http://localhost:8000/api/resume_analysis.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

$response = curl_exec($ch);
$result = json_decode($response, true);
curl_close($ch);

print_r($result);
```

### Python Example
```python
import requests
import json

url = 'http://localhost:8000/api/resume_analysis.php'
data = {
    'resume_text': 'Your resume content here...',
    'job_description': 'Optional job description'
}

response = requests.post(url, json=data)
result = response.json()
print(result)
```

## Testing

### Test Page
A test interface is available at:
```
http://localhost:8000/test_resume_api.html
```

### Integration with Student Dashboard
The API is integrated with the student resume upload system at:
```
http://localhost:8000/resume_analyzer.php
```

## Error Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 400 | Bad Request - Missing or invalid parameters |
| 405 | Method Not Allowed - Only POST is supported |
| 500 | Internal Server Error - API or processing error |

## Rate Limiting
Currently no rate limiting implemented. Consider adding rate limiting for production use.

## Security Notes
1. **API Key Security:** Move API key to environment variables or secure config
2. **Input Validation:** Always validate and sanitize resume text input
3. **CORS:** Configure CORS headers appropriately for production
4. **Authentication:** Add user authentication for production use

## Future Enhancements
- [ ] Support for PDF/DOCX file uploads
- [ ] Batch resume analysis
- [ ] Resume comparison features
- [ ] Industry-specific analysis
- [ ] Multi-language support
- [ ] Resume template suggestions
- [ ] ATS (Applicant Tracking System) compatibility check

## Support
For issues or questions, contact the development team.

## Version
**Version:** 1.0.0  
**Last Updated:** October 10, 2025
