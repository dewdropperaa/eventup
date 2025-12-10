-- Create event_resources table
CREATE TABLE IF NOT EXISTS event_resources (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    nom VARCHAR(255) NOT NULL,
    type ENUM('Salle', 'Matériel', 'Autre') NOT NULL DEFAULT 'Autre',
    quantite_totale INT NOT NULL DEFAULT 1,
    description TEXT,
    date_disponibilite_debut DATE,
    date_disponibilite_fin DATE,
    image_path VARCHAR(255),
    statut ENUM('Disponible', 'Indisponible', 'En maintenance') NOT NULL DEFAULT 'Disponible',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event_id (event_id),
    INDEX idx_statut (statut)
);

-- Create resource_bookings table
CREATE TABLE IF NOT EXISTS resource_bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resource_id INT NOT NULL,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    date_debut DATETIME NOT NULL,
    date_fin DATETIME NOT NULL,
    statut ENUM('Confirmée', 'En attente', 'Annulée') NOT NULL DEFAULT 'Confirmée',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (resource_id) REFERENCES event_resources(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_resource_id (resource_id),
    INDEX idx_user_id (user_id),
    INDEX idx_event_id (event_id),
    INDEX idx_date_debut (date_debut),
    INDEX idx_statut (statut)
);
