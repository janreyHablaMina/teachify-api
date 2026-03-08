# Teachify AI Product and API Plan

## 1. Project Overview
Teachify AI is a SaaS platform that helps teachers generate lessons, quizzes, worksheets, and exams using AI.

Main outcomes:
- Reduce teacher prep time
- Speed up quiz and exam creation
- Automate grading where possible
- Improve classroom performance tracking

## 2. Problem It Solves
Teachers spend too much time on:
- Lesson planning
- Quiz and exam writing
- Grading
- Performance tracking

Teachify AI streamlines this flow:
- Topic or document input
- AI content generation
- Assignment to class
- Student completion
- Auto-grading and analytics

## 3. Target Users
### Teacher (Primary Customer)
- Generate lessons and quizzes
- Upload documents for question generation
- Create classes and assign work
- Track student performance

### Student
- Join classes using a code
- Take assigned quizzes and exams
- View results

### Admin
- Manage users and subscriptions
- Monitor AI usage and platform analytics

## 4. Core Features
### 4.1 AI Lesson Generator
- Prompt-based lesson generation
- Output includes summary, key points, explanations, discussion questions
- Export support: PDF, DOCX, PPTX

### 4.2 AI Quiz Generator
- Generate quizzes by topic and grade level
- Question types: multiple choice, true/false, short answer, essay

### 4.3 Custom Quiz Builder
- Configure question mix before generation
- Example: 10 MC, 8 TF, 2 essay
- Optional timer, passing score, shuffle questions/answers

### 4.4 Document -> Quiz Generator
- Upload source files: PDF, DOCX, PPTX, TXT
- Pipeline: upload -> extract text -> AI analysis -> quiz generation

### 4.5 Auto-Grading System
- Auto-grade objective questions
- Queue essays for teacher review
- Combine auto + manual grading into final score

### 4.6 Classroom System
- Create classrooms and join codes
- Assign quizzes, set deadlines, manage submissions

### 4.7 Quiz and Exam Mode
- Timer and auto-submit
- Question and answer randomization
- Optional anti-cheat controls

### 4.8 Analytics Dashboard
- Class averages
- Student progress
- Most-missed questions
- Weak topic detection

### 4.9 Export Features
- Export quizzes/exams and answer keys
- Formats: PDF, DOCX, PPTX

## 5. Subscription Model
### Plan 1: Quiz Generator
- Price example: $5/month
- Includes AI quiz generation, document upload, and export

### Plan 2: Classroom
- Price example: $12/month
- Includes lesson + quiz generation, classroom tools, analytics, assignments

### Plan 3: School (Future)
- Price example: $49/month
- Includes multi-teacher support, advanced analytics, institution management

## 6. Referral System
- Teachers share referral codes
- Example reward: invite 1 teacher -> 1 free month

## 7. Suggested Technology Stack
### Frontend
- Next.js
- TailwindCSS
- Teacher dashboard, student portal, classroom UI

### Backend
- Laravel 12

### API (organized by domain)
- Auth API: register, login, password reset, token/session handling
- Teacher API: lesson/quiz/worksheet/exam generation endpoints
- Document API: upload, extraction, parsing, document-to-quiz pipeline
- Classroom API: class CRUD, join code, enrollment, assignment lifecycle
- Quiz API: structure config, publish, attempts, timers, randomization
- Grading API: objective auto-grading, essay review queue, final scoring
- Analytics API: class summaries, weak topics, per-student performance
- Subscription API: plans, limits, billing and subscription status
- Export API: PDF/DOCX/PPTX and answer-key generation

### Database
- PostgreSQL

### AI Integration
- OpenAI
- Claude
- DeepSeek

### File Processing
- PDF extraction
- DOCX extraction
- PPTX extraction

## 8. System Workflow
1. Teacher enters prompt or uploads document
2. Teacher configures quiz structure
3. AI generates draft content
4. Teacher reviews and edits
5. Teacher publishes or assigns to class
6. Students complete quiz/exam
7. System auto-grades objective answers
8. Teacher reviews essay answers
9. Final scores and analytics are generated

## 9. MVP Scope
Initial release should include:
- AI quiz generator
- Custom quiz builder
- Document -> quiz generation
- Auto-grading for objective questions
- PDF export

Classroom features can be phased in after launch.

## 10. Future Features
- AI worksheet generator
- AI exam generator
- AI essay grading assistant
- Google Classroom integration
- Multi-language quiz generation
- Question bank and reuse system

## 11. Long-Term Vision
Teachify AI becomes a full AI teaching assistant platform where teachers can:
- Create lessons and assessments
- Manage classes and assignments
- Evaluate outcomes using analytics
- Operate efficiently with AI-assisted workflows
