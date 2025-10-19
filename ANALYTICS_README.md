# Comprehensive Analytics System for Moodle

This project provides a comprehensive analytics dashboard for Moodle that tracks all the metrics you requested, plus a modern frontend registration system.

## Features

### Analytics Dashboard
The system tracks and displays the following comprehensive metrics:

#### 1. Assignment Analytics
- **Average assignment grade %** in each module
- **On-time submission rate %** in each module  
- **Resubmission count**
- **Feedback richness** (length and quality of feedback)

#### 2. Interactive Content Analytics
- **H5P interactions** (interaction count, scores, completion)
- **Video completion %** (view count, completion rates)
- **SCORM scores** (interaction count, average scores)
- **Interactive completion %**
- **Interaction score %**
- **Interactions per minute**

#### 3. Live Instructor Sessions
- **Sessions attended %**
- **Punctuality %**
- **Live minutes attended** (vs session length)
- **Live polls answered %** / reactions
- **Questions asked** / hands raised

#### 4. Forums & Collaboration
- **Posts created** / replies made
- **Response latency**
- **Instructor engagement** received
- **Peer rating**/likes

#### 5. Attendance Analytics
- **Attendance %** (in activities or sessions)
- **Late %** / Absence %
- **Attendance streak**

#### 6. Competency Framework
- **Competency rating**/level attained
- **Proficiency achieved** (Y/N)
- **Evidence count**
- **Date achieved** / last updated
- **Recency-adjusted competency status**

#### 7. Badges & Certificates
- **Badges earned count**
- **Capstone/certificate achieved** (Y/N)
- **Time to certificate**

#### 8. Behavioral Quality & Professionalism
- **Deadline adherence %**
- **Rework acceptance**
- **Learning pace**
- **Persistence**
- **Academic Integrity**
- **Similarity index %**

#### 9. TA / Instructor Evaluation
- **TA rating %**
- **TA notes** (Y/N)

### Frontend Registration System
- Modern, responsive design matching your reference UI
- Direct integration with Moodle user creation
- Clean, professional interface

## Installation

### 1. Moodle Backend Setup

1. **Copy the analytics report files** to your Moodle installation:
   ```
   cp -r report/analytics/ /path/to/moodle/grade/report/
   ```

2. **Enable web services** in Moodle:
   - Go to Site administration → Advanced features
   - Enable "Web services"
   - Go to Site administration → Plugins → Web services → Manage protocols
   - Enable "REST"

3. **Create external service**:
   - Go to Site administration → Plugins → Web services → External services
   - Add a new service
   - Add the function: `gradereport_analytics_get_comprehensive_analytics`
   - Create a service token

4. **Set up capabilities**:
   - Go to Site administration → Users → Permissions → Define roles
   - Edit Teacher/Manager role
   - Add capability: `gradereport/analytics:view`

### 2. Frontend Setup

1. **Configure Moodle connection** in `frontend/index.html`:
   ```javascript
   const MOODLE_URL = 'https://your-moodle-site.com';
   const WS_TOKEN = 'your-service-token';
   ```

2. **Configure analytics dashboard** in `frontend/analytics-dashboard.html`:
   ```javascript
   const MOODLE_URL = 'https://your-moodle-site.com';
   const WS_TOKEN = 'your-service-token';
   const COURSE_ID = 1; // Your course ID
   ```

3. **Deploy frontend files** to your web server or host them within Moodle

### 3. Access the Analytics Dashboard

1. **From Moodle**: Navigate to `/grade/report/analytics/index.php?id=COURSE_ID`
2. **Standalone**: Open `frontend/analytics-dashboard.html` in a browser

## Usage

### Analytics Dashboard

The dashboard provides multiple views:

1. **Overview Tab**: Key metrics and student performance summary
2. **Assignments Tab**: Detailed assignment analytics
3. **Interactive Content Tab**: H5P, video, and SCORM engagement
4. **Live Sessions Tab**: Synchronous session participation
5. **Forums Tab**: Discussion and collaboration metrics
6. **Attendance Tab**: Attendance patterns and streaks
7. **Competencies Tab**: Competency framework progress
8. **Behavioral Tab**: Professionalism and integrity metrics

### Export Options

- **CSV Export**: Download comprehensive data for external analysis
- **JSON Export**: API access for integration with other systems

### Registration System

The registration system allows users to:
- Create accounts directly from the frontend
- Automatically sync with Moodle user database
- Maintain consistent design with your reference UI

## API Endpoints

### Get Comprehensive Analytics
```
POST /webservice/rest/server.php
Parameters:
- wstoken: Your service token
- wsfunction: gradereport_analytics_get_comprehensive_analytics
- moodlewsrestformat: json
- courseid: Course ID
- userid: Optional user ID (0 for all users)
```

### Response Format
The API returns detailed analytics data including all metrics mentioned above, structured by user and category.

## Data Sources

The system pulls data from various Moodle tables:

- `grade_grades` - Assignment grades and feedback
- `assign_submission` - Assignment submissions and timing
- `course_modules_completion` - Activity completion
- `logstore_standard_log` - User activity logs
- `forum_posts` - Forum participation
- `badge_issued` - Badge achievements
- `competency_usercomp` - Competency progress
- `hvp_content_user_data` - H5P interactions (if H5P plugin installed)
- `scorm_scoes_track` - SCORM interactions (if SCORM activities exist)
- `bigbluebuttonbn_logs` - BigBlueButton sessions (if BBB plugin installed)

## Customization

### Adding New Metrics

1. **Extend the data collection** in `report/analytics/lib.php`
2. **Update the web service** in `report/analytics/classes/external/get_comprehensive_analytics.php`
3. **Add UI components** in the dashboard templates

### Styling

The dashboard uses Bootstrap 5 and custom CSS. You can customize:
- Color scheme in the CSS variables
- Chart types and configurations
- Table layouts and styling

## Security Considerations

1. **Service Tokens**: Keep your web service tokens secure
2. **Permissions**: Ensure only authorized users can access analytics
3. **Data Privacy**: Consider GDPR compliance for student data
4. **CORS**: Configure CORS if hosting frontend separately

## Troubleshooting

### Common Issues

1. **"No data found"**: Check course enrollment and permissions
2. **"Web service error"**: Verify token and service configuration
3. **"Missing tables"**: Some metrics require specific plugins (H5P, BBB, etc.)

### Debug Mode

Enable debug mode in Moodle to see detailed error messages.

## Support

For issues or customization requests, please refer to the Moodle documentation or contact your system administrator.

## License

This project follows the same license as Moodle (GPL v3 or later).
