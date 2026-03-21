CREATE TABLE users (
  id_users BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255),
  email VARCHAR(255),
  password_hash VARCHAR(255),
  role VARCHAR(50),
  department VARCHAR(100),
  is_active TINYINT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);

CREATE TABLE badges (
  id_badges BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255),
  description TEXT,
  icon VARCHAR(255)
);

CREATE TABLE articles (
  id_articles BIGINT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255),
  content TEXT,
  category VARCHAR(100),
  created_at TIMESTAMP,
  id_users_created_by BIGINT,
  FOREIGN KEY (id_users_created_by) REFERENCES users(id_users)
);

CREATE TABLE bmi_records (
  id_bmi_records BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_users BIGINT,
  height_cm INT,
  weight_kg DECIMAL(5,2),
  bmi_value DECIMAL(5,2),
  recorded_at DATE,
  FOREIGN KEY (id_users) REFERENCES users(id_users)
);

CREATE TABLE coaches (
  id_coaches BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_users BIGINT,
  coach_name VARCHAR(255),
  coach_email VARCHAR(255),
  coach_phone VARCHAR(50),
  coach_bio TEXT,
  specialties_text TEXT,
  coach_type VARCHAR(100),
  rate_type VARCHAR(100),
  rate_text VARCHAR(100),
  visibility VARCHAR(50),
  department_scope VARCHAR(100),
  id_users_created_by BIGINT,
  is_active TINYINT,
  created_at TIMESTAMP,
  FOREIGN KEY (id_users) REFERENCES users(id_users),
  FOREIGN KEY (id_users_created_by) REFERENCES users(id_users)
);

CREATE TABLE coach_sessions (
  id_coach_sessions BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_coaches BIGINT,
  id_users BIGINT,
  session_date DATE,
  start_time TIME,
  end_time TIME,
  location_text VARCHAR(255),
  notes TEXT,
  status VARCHAR(50),
  created_at TIMESTAMP,
  FOREIGN KEY (id_coaches) REFERENCES coaches(id_coaches),
  FOREIGN KEY (id_users) REFERENCES users(id_users)
);

CREATE TABLE daily_checkins (
  id_daily_checkins BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_users BIGINT,
  activity_minutes INT,
  water_intake_ml INT,
  checkin_date DATE,
  created_at TIMESTAMP,
  FOREIGN KEY (id_users) REFERENCES users(id_users)
);

CREATE TABLE events (
  id_events BIGINT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255),
  description TEXT,
  event_type VARCHAR(100),
  start_date DATE,
  end_date DATE,
  reward_points INT,
  is_active TINYINT
);

CREATE TABLE event_participants (
  id_event_participants BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_events BIGINT,
  id_users BIGINT,
  status VARCHAR(50),
  FOREIGN KEY (id_events) REFERENCES events(id_events),
  FOREIGN KEY (id_users) REFERENCES users(id_users)
);

CREATE TABLE foods (
  id_foods BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255),
  calories INT,
  protein DECIMAL(6,2),
  fat DECIMAL(6,2),
  carbs DECIMAL(6,2),
  rating INT,
  id_users_created_by BIGINT,
  FOREIGN KEY (id_users_created_by) REFERENCES users(id_users)
);

CREATE TABLE food_logs (
  id_food_logs BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_users BIGINT,
  id_foods BIGINT,
  consumed_at DATE,
  FOREIGN KEY (id_users) REFERENCES users(id_users),
  FOREIGN KEY (id_foods) REFERENCES foods(id_foods)
);

CREATE TABLE gyms (
  id_gyms BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255),
  location VARCHAR(255),
  is_active TINYINT
);

CREATE TABLE gym_equipments (
  id_gym_equipments BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_gyms BIGINT,
  equipment_name VARCHAR(255),
  quantity INT,
  FOREIGN KEY (id_gyms) REFERENCES gyms(id_gyms)
);

CREATE TABLE gym_bookings (
  id_gym_bookings BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_users BIGINT,
  id_gyms BIGINT,
  booking_date DATE,
  time_slot VARCHAR(50),
  status VARCHAR(50),
  FOREIGN KEY (id_users) REFERENCES users(id_users),
  FOREIGN KEY (id_gyms) REFERENCES gyms(id_gyms)
);

CREATE TABLE mens_user_profiles (
  id_users BIGINT PRIMARY KEY,
  birth_year INT,
  birth_month INT,
  birth_day INT,
  birth_hour INT,
  updated_at TIMESTAMP,
  FOREIGN KEY (id_users) REFERENCES users(id_users)
);

CREATE TABLE mens_cycle_logs (
  id_mens_cycle_logs BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_users BIGINT,
  period_start_date DATE,
  reported_cycle_length INT,
  period_length INT,
  symptom_score INT,
  mood_score INT,
  created_at TIMESTAMP,
  period_end_date DATE,
  mood_type VARCHAR(50),
  symptom_tags TEXT,
  flow_level VARCHAR(50),
  FOREIGN KEY (id_users) REFERENCES users(id_users)
);

CREATE TABLE point_logs (
  id_point_logs BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_users BIGINT,
  source VARCHAR(100),
  points INT,
  description VARCHAR(255),
  created_at TIMESTAMP,
  FOREIGN KEY (id_users) REFERENCES users(id_users)
);

CREATE TABLE sleep_logs (
  id_sleep_logs BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_users BIGINT,
  sleep_date DATE,
  sleep_start TIME,
  sleep_end TIME,
  total_sleep_hours DECIMAL(4,2),
  sleep_quality VARCHAR(50),
  late_night TINYINT,
  note TEXT,
  created_at TIMESTAMP,
  FOREIGN KEY (id_users) REFERENCES users(id_users)
);

CREATE TABLE user_points (
  id_user_points BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_users BIGINT,
  total_points INT,
  updated_at TIMESTAMP,
  FOREIGN KEY (id_users) REFERENCES users(id_users)
);

CREATE TABLE user_badges (
  id_user_badges BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_users BIGINT,
  id_badges BIGINT,
  earned_at TIMESTAMP,
  FOREIGN KEY (id_users) REFERENCES users(id_users),
  FOREIGN KEY (id_badges) REFERENCES badges(id_badges)
);

CREATE TABLE user_sessions (
  id_user_sessions BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_users BIGINT,
  session_token VARCHAR(255),
  login_at TIMESTAMP,
  logout_at TIMESTAMP,
  ip_address VARCHAR(50),
  user_agent TEXT,
  FOREIGN KEY (id_users) REFERENCES users(id_users)
);

CREATE TABLE user_streaks (
  id_user_streaks BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_users BIGINT,
  streak_type VARCHAR(50),
  current_streak INT,
  longest_streak INT,
  updated_at TIMESTAMP,
  FOREIGN KEY (id_users) REFERENCES users(id_users)
);

CREATE TABLE workout_personalizations (
  id_workout_personalizations BIGINT PRIMARY KEY AUTO_INCREMENT,
  id_users BIGINT,
  goal VARCHAR(100),
  fitness_level VARCHAR(100),
  detail_workout TEXT,
  notes TEXT,
  created_at TIMESTAMP,
  name VARCHAR(255),
  FOREIGN KEY (id_users) REFERENCES users(id_users)
);
