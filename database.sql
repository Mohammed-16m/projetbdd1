CREATE DATABASE IF NOT EXISTS TEST;
USE TEST;

-- 1. Départements 
CREATE TABLE departements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE,
    etat_planning ENUM('en_attente', 'valide', 'publie') DEFAULT 'en_attente'
);

-- 2. Utilisateurs
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'chef_dep', 'doyen', 'professeur', 'etudiant') NOT NULL
);

-- 3. Lieux d'examen
CREATE TABLE lieu_examen (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50),
    capacite INT,
    type VARCHAR(50),
    batiment VARCHAR(50)
);

-- 4. Formations
CREATE TABLE formations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    dept_id INT,
    CONSTRAINT fk_form_dept FOREIGN KEY (dept_id) REFERENCES departements(id) ON DELETE SET NULL
);

-- 5. Modules
CREATE TABLE modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    credits INT,
    formation_id INT,
    CONSTRAINT fk_mod_form FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE CASCADE
);

-- 6. Étudiants
CREATE TABLE etudiants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    formation_id INT,
    promo VARCHAR(10),
    user_id INT,
    CONSTRAINT fk_etu_form FOREIGN KEY (formation_id) REFERENCES formations(id) ON DELETE SET NULL,
    CONSTRAINT fk_etu_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 7. Professeurs
CREATE TABLE professeurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100),
    dept_id INT,
    specialite VARCHAR(100),
    user_id INT,
    CONSTRAINT fk_prof_dept FOREIGN KEY (dept_id) REFERENCES departements(id) ON DELETE SET NULL,
    CONSTRAINT fk_prof_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 8. Inscriptions 
CREATE TABLE inscriptions (
    etudiant_id INT,
    module_id INT,
    salle_id INT DEFAULT NULL, 
    note DECIMAL(5,2) DEFAULT NULL,
    PRIMARY KEY (etudiant_id, module_id),
    CONSTRAINT fk_ins_etu FOREIGN KEY (etudiant_id) REFERENCES etudiants(id) ON DELETE CASCADE,
    CONSTRAINT fk_ins_mod FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    CONSTRAINT fk_ins_salle FOREIGN KEY (salle_id) REFERENCES lieu_examen(id) ON DELETE SET NULL
);

-- 9. Examens 
CREATE TABLE examens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_id INT,
    prof_id INT,
    salle_id INT,
    date_heure DATETIME,
    duree_minutes INT DEFAULT 90,
    CONSTRAINT fk_ex_mod FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    CONSTRAINT fk_ex_prof FOREIGN KEY (prof_id) REFERENCES professeurs(id) ON DELETE CASCADE,
    CONSTRAINT fk_ex_salle FOREIGN KEY (salle_id) REFERENCES lieu_examen(id) ON DELETE CASCADE
);



-- Insertion des Départements
INSERT INTO departements (id, nom) VALUES (1, 'Informatique'), (2, 'Mathématiques'), (3, 'Physique');


INSERT INTO users (username, password, role) VALUES 
('admin', 'admin123', 'admin'),
('doyen', 'doyen123', 'doyen'),
('chef1', 'chef123', 'chef_dep'),
('chef2', 'chef123', 'chef_dep'),
('chef3', 'chef123', 'chef_dep');

-- Insertion des Formations et Modules de base
INSERT INTO formations (id, nom, dept_id) VALUES (1, 'Licence Informatique', 1), (2, 'Licence Maths', 2), (3, 'Licence Physique', 3);

INSERT INTO modules (nom, formation_id) VALUES 
('Algorithmique', 1), ('Systèmes', 1), ('Réseaux', 1), ('BDD', 1), ('Web', 1),
('Analyse', 2), ('Algèbre', 2), ('Probabilités', 2), ('Statistiques', 2), ('Géométrie', 2),
('Mécanique', 3), ('Optique', 3), ('Thermodynamique', 3), ('Électricité', 3), ('Nucléaire', 3);
