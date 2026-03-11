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
  created_by BIGINT,
  FOREIGN KEY (created_by) REFERENCES users(id_users)
);

CREATE TABLE bmi_records (
  id_bmi_records BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
  height_cm INT,
  weight_kg DECIMAL(5,2),
  bmi_value DECIMAL(5,2),
  recorded_at DATE,
  FOREIGN KEY (user_id) REFERENCES users(id_users)
);

CREATE TABLE coaches (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
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
  created_by BIGINT,
  is_active TINYINT,
  created_at TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id_users),
  FOREIGN KEY (created_by) REFERENCES users(id_users)
);

CREATE TABLE coach_sessions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  coach_id BIGINT,
  requester_user_id BIGINT,
  session_date DATE,
  start_time TIME,
  end_time TIME,
  location_text VARCHAR(255),
  notes TEXT,
  status VARCHAR(50),
  created_at TIMESTAMP,
  FOREIGN KEY (coach_id) REFERENCES coaches(id),
  FOREIGN KEY (requester_user_id) REFERENCES users(id_users)
);

CREATE TABLE daily_checkins (
  id_daily_checkins BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
  activity_minutes INT,
  water_intake_ml INT,
  checkin_date DATE,
  created_at TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id_users)
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
  event_id BIGINT,
  user_id BIGINT,
  status VARCHAR(50),
  FOREIGN KEY (event_id) REFERENCES events(id_events),
  FOREIGN KEY (user_id) REFERENCES users(id_users)
);

CREATE TABLE foods (
  id_foods BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255),
  calories INT,
  protein DECIMAL(6,2),
  fat DECIMAL(6,2),
  carbs DECIMAL(6,2),
  rating INT,
  created_by BIGINT,
  FOREIGN KEY (created_by) REFERENCES users(id_users)
);

CREATE TABLE food_logs (
  id_food_logs BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
  food_id BIGINT,
  consumed_at DATE,
  FOREIGN KEY (user_id) REFERENCES users(id_users),
  FOREIGN KEY (food_id) REFERENCES foods(id_foods)
);

CREATE TABLE gyms (
  id_gyms BIGINT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(255),
  location VARCHAR(255),
  is_active TINYINT
);

CREATE TABLE gym_equipments (
  id_gym_equipments BIGINT PRIMARY KEY AUTO_INCREMENT,
  gym_id BIGINT,
  equipment_name VARCHAR(255),
  quantity INT,
  FOREIGN KEY (gym_id) REFERENCES gyms(id_gyms)
);

CREATE TABLE gym_bookings (
  id_gym_bookings BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
  gym_id BIGINT,
  booking_date DATE,
  time_slot VARCHAR(50),
  status VARCHAR(50),
  FOREIGN KEY (user_id) REFERENCES users(id_users),
  FOREIGN KEY (gym_id) REFERENCES gyms(id_gyms)
);

CREATE TABLE mens_user_profiles (
  user_id BIGINT PRIMARY KEY,
  birth_year INT,
  birth_month INT,
  birth_day INT,
  birth_hour INT,
  updated_at TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id_users)
);

CREATE TABLE mens_cycle_logs (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
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
  FOREIGN KEY (user_id) REFERENCES users(id_users)
);

CREATE TABLE point_logs (
  id_point_logs BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
  source VARCHAR(100),
  points INT,
  description VARCHAR(255),
  created_at TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id_users)
);

CREATE TABLE sleep_logs (
  id_sleep_logs BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
  sleep_date DATE,
  sleep_start TIME,
  sleep_end TIME,
  total_sleep_hours DECIMAL(4,2),
  sleep_quality VARCHAR(50),
  late_night TINYINT,
  note TEXT,
  created_at TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id_users)
);

CREATE TABLE user_points (
  id_user_points BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
  total_points INT,
  updated_at TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id_users)
);

CREATE TABLE user_badges (
  id_user_badges BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
  badge_id BIGINT,
  earned_at TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id_users),
  FOREIGN KEY (badge_id) REFERENCES badges(id_badges)
);

CREATE TABLE user_sessions (
  id_user_sessions BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
  session_token VARCHAR(255),
  login_at TIMESTAMP,
  logout_at TIMESTAMP,
  ip_address VARCHAR(50),
  user_agent TEXT,
  FOREIGN KEY (user_id) REFERENCES users(id_users)
);

CREATE TABLE user_streaks (
  id_user_streaks BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
  streak_type VARCHAR(50),
  current_streak INT,
  longest_streak INT,
  updated_at TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id_users)
);

CREATE TABLE workout_personalizations (
  id_workout BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
  goal VARCHAR(100),
  fitness_level VARCHAR(100),
  detail_workout TEXT,
  notes TEXT,
  created_at TIMESTAMP,
  name VARCHAR(255),
  FOREIGN KEY (user_id) REFERENCES users(id_users)
);