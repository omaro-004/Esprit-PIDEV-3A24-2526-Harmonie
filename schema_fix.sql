SET SESSION sql_mode = REPLACE(REPLACE(@@SESSION.sql_mode,'NO_ZERO_DATE',''),'NO_ZERO_IN_DATE','');

ALTER TABLE seance DROP FOREIGN KEY `fk_seance_salle`;
ALTER TABLE seance CHANGE date_fin date_fin DATETIME NULL DEFAULT NULL;
UPDATE seance SET date_fin = date_debut WHERE date_fin IS NULL;
ALTER TABLE seance CHANGE description description LONGTEXT DEFAULT NULL, CHANGE date_debut date_debut DATETIME NOT NULL, CHANGE date_fin date_fin DATETIME NOT NULL, CHANGE confirmee confirmee TINYINT NOT NULL, CHANGE date_creation date_creation DATETIME NOT NULL;
DROP INDEX fk_seance_salle ON seance;
CREATE INDEX IDX_DF7DFD0EDC304035 ON seance (salle_id);
ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0EDC304035 FOREIGN KEY (salle_id) REFERENCES salle (id);

ALTER TABLE user CHANGE user_sexe user_sexe ENUM('HOMME','FEMME','AUTRE') DEFAULT NULL, CHANGE user_niveau_activite_physique user_niveau_activite_physique ENUM('SEDENTAIRE','LEGER','MODERE','INTENSE','TRES_INTENSE') DEFAULT NULL, CHANGE user_niveau_scolaire user_niveau_scolaire ENUM('PRIMAIRE','COLLEGE','LYCEE','LICENCE','MASTER','DOCTORAT','AUTRE') DEFAULT NULL, CHANGE type_utilisateur type_utilisateur ENUM('ETUDIANT','ADMIN') NOT NULL DEFAULT 'ETUDIANT';
