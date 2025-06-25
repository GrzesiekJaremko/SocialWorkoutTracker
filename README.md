# 💪 Social Workout Tracker
A full-featured social fitness web app that empowers users to track their workouts, customize training plans, connect with friends, and compare progress — all in one mobile-optimized experience.

# 🚀 Features
## 👤 User Accounts & Profiles
- Secure signup and login system

- Customizable profiles and avatars

- Private stat pages (viewable only by you or your friends)

## 📅 Workout Tracking
- Create fully custom Workout Plans (e.g., Push Day, Pull Day)

- Add detailed Workouts with sets, reps, and weights

- Automatically tracks your:

- Last gym session

- Personal records (PRs)

- Total sets and sessions completed

## 📊 Progress Graphs
- Visualize your performance over time with dynamic charts

- Filter by last month, 3 months, 6 months, 12 months, or All Time

- Helps users stay motivated and goal-driven

- Metrics include total volume, frequency, and personal bests

## 👯 Social Features
- Search for users and send friend requests

- Friends must accept before you can share data

- Once connected:

- View each other’s workout history and stats

- Send direct messages

- Compare lifts and session history

## 🌐 How to Access the Demo
Experience the full functionality of the Social Workout Tracker by logging in with the demo credentials below:

Login: Demo-account

Password: D3m0nStrat10n

Feel free to:

- Create workout plans

- Log exercises and sessions

- Explore the social features (search, friend requests, messaging)

- View your progress graphs

## 📱 Recommended Usage
This is a mobile-first web app, and it's best experienced on a smartphone.

🔗 Visit the app on mobile - https://workouttracker.free.nf/

📷 Scan the QR code (available on my portfolio site) for quick access

🖥️ You can also explore it on desktop for development review

## 🛠️ Tech Stack
| Technology | Role                          |
| ---------- | ----------------------------- |
| PHP        | Backend/server logic          |
| SQL        | Data storage & relationships  |
| HTML/CSS   | Frontend layout and styling   |
| JavaScript | Interactive elements & graphs |

Progress charts are rendered using client-side JavaScript libraries like Chart.js.

## 🧠 What I Learned
Through building this app, I developed strong foundations in:

- Authentication and session handling

- Mobile-first responsive design

- Real-time data visualization (graphs)

- CRUD operations and relational database design

- Social systems (friends, privacy, messaging)

- UX considerations for fitness users

- I also gained firsthand experience in maintaining and debugging a growing codebase. Some files — particularly CSS and JS — are written page-specifically rather than modularly. While this was a learning step, I now understand the importance of clean, scalable structure and will apply that in future work.

## 📂 Project Structure
| Path                          | Description                                        |
| ----------------------------- | -------------------------------------------------- |
| `/htdocs/`                    | **Main app directory** (root folder)               |
| ├── `index.php`               | App landing page (login or redirect to homepage)   |
| ├── `add_workout_log.php`     | Add a new workout log                              |
| ├── `add_workout_plan.php`    | Create a new workout plan                          |
| ├── `connect.php`             | Database connection configuration                  |
| ├── `delete_workout.php`      | Delete an individual workout from a plan           |
| ├── `delete_workout_plan.php` | Delete an entire workout plan                      |
| ├── `edit_workout.php`        | Edit a specific workout                            |
| ├── `edit_workout_plan.php`   | Rename or edit a workout plan                      |
| ├── `forgot_password.php`     | Password reset request form                        |
| ├── `reset_password.php`      | Handle new password setup after reset              |
| ├── `friends.php`             | Friend request system and friend list UI           |
| ├── `homepage.php`            | Main user dashboard (view plans, progress, social) |
| ├── `logout.php`              | Log out the current user                           |
| ├── `messages.php`            | Direct messaging between friends                   |
| ├── `profile.php`             | View and edit user profile                         |
| ├── `progress.php`            | Progress graphs and personal record stats          |
| ├── `register.php`            | Create a new user account                          |
| ├── `search.php`              | Displays searched Profile                          |
| ├── `search_result.php`       | Displays results from a user search                |
| ├── `usersplan.php`           | Handles logic for displaying a user’s workout plan |
| ├── `view_workout_plan.php`   | View details and workouts under a specific plan    |
| ├── `style.css`               | Global and page-specific styling                   |
| ├── `script.js`               | Core JS logic and page-specific behavior           |
| /assets/                      | Static images and icons                            |
|  └── arrow.png                | Navigation or back arrow icon                      |
|  └── messages.png             | Icon for the messaging system                      |
|  └── friends.png              | Icon for friends section                           |
|  └── logo.png                 | Application logo                                   |
| /uploads/                     | Uploaded media (profile images, etc.)              |
|  └── /profile_pictures/       | Directory containing profile images                |
|  └── default.jpg              | Default profile image for new users                |

## 👋 About Me
I'm an aspiring full-stack developer with a passion for fitness and creating tools that drive real-world results. This project represents a major learning milestone in both frontend and backend development, and I’m proud of what I’ve built-Mistakes included!

# Let’s connect:
👔 https://www.linkedin.com/in/greg-jaremko-594739291/ · 📧 jaremkog@icloud.com · 💼 https://grzesiekjaremko.github.io/
