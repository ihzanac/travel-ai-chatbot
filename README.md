# ✈️ Travel AI Chatbot

## 🤖 AI-Powered Smart Tourism Assistant

Travel AI Chatbot is an AI-powered web-based tourism assistant designed to help users discover travel destinations, tourist attractions, hotels, and other travel-related information through an interactive chatbot.

The system provides personalized travel assistance and allows users to communicate with an AI chatbot without refreshing the web page.

---

## 🌍 Project Overview

The Travel AI Chatbot is developed to make travel planning easier and more convenient.

Users can ask questions about:

- 📍 Travel destinations
- 🏖️ Tourist attractions
- 🏨 Hotels and accommodation
- 💰 Travel budgets
- 🗺️ Places to visit
- 🚗 Travel information
- 🌴 Tourism activities

The system also provides an administration panel for managing users and tourism-related information.

---

## ✨ Main Features

### 👤 Customer Features

- User Registration
- User Login
- User Authentication
- AI Travel Chatbot
- Destination Search
- Tourist Place Information
- Hotel Information
- Travel Budget Assistance
- Travel Recommendations
- Chat Session Management
- Responsive User Interface

### 🤖 AI Chatbot

The AI chatbot allows users to ask travel-related questions such as:

> "What are the best places to visit in Sri Lanka?"

> "Suggest hotels in Colombo."

> "What is the budget for a trip to Kandy?"

> "What are the tourist attractions in Batticaloa?"

The chatbot processes the user's question and provides travel-related responses using the AI API.

### 👨‍💼 Admin Features

- Admin Login
- Admin Dashboard
- User Management
- Tourism Data Management
- Destination Management
- Travel Information Management
- Chat Session Management
- System Monitoring

---

## 🛠️ Technologies Used

### Frontend

- HTML5
- CSS3
- JavaScript
- Bootstrap 5

### Backend

- PHP

### Database

- MySQL

### AI

- Google Gemini API

### Communication

- AJAX
- Fetch API

### Development Environment

- XAMPP
- Visual Studio Code
- Git
- GitHub

---

## 📂 Project Structure

```text
Travel AI Chatbot/
│
├── admin/
│   ├── css/
│   ├── js/
│   ├── login.php
│   ├── logout.php
│   └── panel.php
│
├── api/
│   ├── admin/
│   ├── auth/
│   └── chat/
│
├── common/
│   └── css/
│
├── config/
│
├── customer/
│   ├── css/
│   └── js/
│
├── includes/
│   ├── .htaccess
│   └── database.php
│
├── tools/
│
├── admin_api.php
├── admin_login.php
├── admin_logout.php
├── admin_panel.php
├── auth.php
├── chat_sessions.php
├── chatbot.php
├── database.sql
├── db.php
├── db_check.php
├── index.html
├── mysqli_check.php
├── phpinfo_web.php
├── .gitignore
└── README.md
```

---

## 🚀 Installation

### Step 1 — Install XAMPP

Install XAMPP on your computer.

Start the following services:

```text
Apache
MySQL
```

### Step 2 — Clone the Repository

```bash
git clone https://github.com/ihzanac/travel-ai-chatbot.git
```

### Step 3 — Move Project to XAMPP

Place the project inside:

```text
C:\xampp\htdocs\
```

Project path:

```text
C:\xampp\htdocs\Travel AI Chatbot
```

### Step 4 — Start XAMPP

Open XAMPP Control Panel and start:

```text
Apache
MySQL
```

### Step 5 — Create MySQL Database

Open:

```text
http://localhost/phpmyadmin
```

Create the required database and import:

```text
database.sql
```

### Step 6 — Configure Database

Configure the database connection using the project's configuration files.

Example:

```php
$host = "localhost";
$username = "root";
$password = "";
$database = "travel_ai_chatbot";
```

Use the actual database name configured for the project.

### Step 7 — Configure Gemini API

Configure the Gemini API key in the local Gemini configuration.

Do not upload API keys or secret credentials to GitHub.

---

## ▶️ Run the Project

Open the browser and visit:

```text
http://localhost/Travel%20AI%20Chatbot/
```

---

## 🔐 Security

Sensitive files and credentials should not be uploaded to GitHub.

The `.gitignore` file is used to prevent sensitive configuration files from being committed.

Examples:

```text
.env
.env.*
config.local.php
config.php
config/gemini.php
```

Never publish:

- API Keys
- Database passwords
- Private credentials
- Secret tokens

---

## 💬 Example Chatbot Questions

```text
Best places to visit in Sri Lanka?

What are the tourist attractions in Kandy?

Suggest hotels in Colombo.

What is the travel budget for Ella?

Places to visit in Batticaloa?

Best time to visit Nuwara Eliya?
```

---

## 🎯 Project Objectives

The main objectives of the Travel AI Chatbot are:

1. Provide intelligent travel assistance.
2. Help users discover tourism destinations.
3. Provide useful travel-related information.
4. Reduce the difficulty of planning trips.
5. Provide an interactive AI-based chatbot.
6. Manage tourism information through an admin panel.
7. Create a user-friendly tourism assistance platform.

---

## 🏗️ System Architecture

```text
                 ┌─────────────────────┐
                 │       User          │
                 └──────────┬──────────┘
                            │
                            ▼
                 ┌─────────────────────┐
                 │   Web Application   │
                 │ HTML/CSS/JS/Bootstrap│
                 └──────────┬──────────┘
                            │
                            ▼
                 ┌─────────────────────┐
                 │      PHP Backend    │
                 └───────┬───────┬─────┘
                         │       │
              ┌──────────┘       └──────────┐
              ▼                             ▼
     ┌─────────────────┐          ┌─────────────────┐
     │     MySQL       │          │   Gemini API    │
     │    Database     │          │  AI Chatbot     │
     └─────────────────┘          └─────────────────┘
```

---

## 👥 User Roles

### Customer

Customers can:

- Register
- Login
- Use the AI chatbot
- Search travel information
- Explore destinations
- View tourism information
- Manage chat sessions

### Administrator

Administrators can:

- Login securely
- Manage users
- Manage tourism information
- Manage destinations
- Monitor system data
- Manage chatbot-related information

---

## 📱 Responsive Design

The system is designed to work with different screen sizes:

- 💻 Desktop
- 💻 Laptop
- 📱 Mobile
- 📱 Tablet

---

## 🎓 Academic Project

This project is developed as an academic software engineering project to demonstrate the practical application of:

- Web Development
- Artificial Intelligence
- Database Management
- API Integration
- Software Engineering
- User Authentication
- System Design

---

## 👨‍💻 Developer

**Ihzan AC**

**BEng (Hons) Software Engineering Graduate**

---

## 🔗 GitHub Repository

https://github.com/ihzanac/travel-ai-chatbot

---

## 📄 License

This project is developed for educational and academic purposes.
