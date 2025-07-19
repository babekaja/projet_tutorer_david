-- Base de données UCB Transport
-- Script de création complet

CREATE DATABASE IF NOT EXISTS ucb_transport CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ucb_transport;

-- Table des étudiants (utilisateurs)
CREATE TABLE IF NOT EXISTS etudiants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricule VARCHAR(20) UNIQUE NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    telephone VARCHAR(20),
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('actif', 'inactif') DEFAULT 'actif'
);

-- Table des administrateurs
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'super_admin') DEFAULT 'admin',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    derniere_connexion TIMESTAMP NULL
);

-- Table des trajets
CREATE TABLE IF NOT EXISTS trajets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom_trajet VARCHAR(200) NOT NULL,
    point_depart VARCHAR(150) NOT NULL,
    point_arrivee VARCHAR(150) NOT NULL,
    date_depart DATE NOT NULL,
    heure_depart TIME NOT NULL,
    capacite INT NOT NULL DEFAULT 50,
    prix DECIMAL(10,2) DEFAULT 0.00,
    description TEXT,
    statut ENUM('actif', 'annule', 'complet') DEFAULT 'actif',
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des réservations
CREATE TABLE IF NOT EXISTS reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    etudiant_id INT NOT NULL,
    trajet_id INT NOT NULL,
    numero_billet VARCHAR(50) UNIQUE NOT NULL,
    date_reservation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    statut ENUM('reserve', 'valide', 'annule', 'utilise') DEFAULT 'reserve',
    qr_code_path VARCHAR(255),
    notes TEXT,
    FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    FOREIGN KEY (trajet_id) REFERENCES trajets(id) ON DELETE CASCADE,
    UNIQUE KEY unique_reservation (etudiant_id, trajet_id)
);

-- Insertion des données de test

-- Admin par défaut (mot de passe: admin123)
INSERT INTO admins (username, nom, email, password) VALUES 
('admin', 'Administrateur UCB', 'admin@ucb.ac.cd', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Étudiants de test (mot de passe: etudiant123)
INSERT INTO etudiants (matricule, nom, prenom, email, password, telephone) VALUES 
('UCB2024001', 'Mukamba', 'Jean', 'jean.mukamba@ucb.ac.cd', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+243970123456'),
('UCB2024002', 'Nabintu', 'Marie', 'marie.nabintu@ucb.ac.cd', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+243970123457'),
('UCB2024003', 'Bahati', 'Pierre', 'pierre.bahati@ucb.ac.cd', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+243970123458');

-- Trajets de test
INSERT INTO trajets (nom_trajet, point_depart, point_arrivee, date_depart, heure_depart, capacite, prix, description) VALUES 
('UCB - Centre Ville', 'Université Catholique de Bukavu', 'Centre Ville Bukavu', '2025-01-20', '07:30:00', 45, 500.00, 'Trajet matinal vers le centre ville'),
('Centre Ville - UCB', 'Centre Ville Bukavu', 'Université Catholique de Bukavu', '2025-01-20', '17:00:00', 45, 500.00, 'Trajet retour vers l\'université'),
('UCB - Aéroport', 'Université Catholique de Bukavu', 'Aéroport de Bukavu', '2025-01-22', '14:00:00', 30, 1500.00, 'Navette spéciale vers l\'aéroport'),
('UCB - Goma', 'Université Catholique de Bukavu', 'Goma', '2025-01-25', '06:00:00', 50, 5000.00, 'Voyage inter-urbain vers Goma');

-- Index pour optimiser les performances
CREATE INDEX idx_reservations_etudiant ON reservations(etudiant_id);
CREATE INDEX idx_reservations_trajet ON reservations(trajet_id);
CREATE INDEX idx_trajets_date ON trajets(date_depart, heure_depart);
CREATE INDEX idx_etudiants_matricule ON etudiants(matricule);