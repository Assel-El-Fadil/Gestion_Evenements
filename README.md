# ClubConnect - Système de Gestion des Clubs Étudiants

ClubConnect est une plateforme web complète de gestion des clubs étudiants qui permet aux utilisateurs de découvrir, s'inscrire et participer aux événements organisés par différents clubs de l'université.

## 🚀 Fonctionnalités

### Pour les Étudiants (Utilisateurs)
- **Découverte d'événements** : Parcourez les événements à venir organisés par les clubs
- **Inscription aux événements** : Inscrivez-vous facilement aux événements qui vous intéressent
- **Gestion de profil** : Consultez vos informations personnelles et votre département
- **Mes événements** : Suivez vos événements à venir et passés
- **Mes clubs** : Consultez les clubs auxquels vous appartenez
- **Certificats de participation** : Téléchargez automatiquement vos certificats pour les événements terminés

### Pour les Organisateurs
- **Tableau de bord complet** : Vue d'ensemble de vos activités et statistiques
- **Création d'événements** : Créez et gérez vos événements avec photos et détails complets
- **Gestion des participants** : Suivez les inscriptions et la capacité de vos événements
- **Communication** : Système de messagerie intégré
- **Génération de certificats** : Certificats automatiques pour les participants

## 🛠️ Technologies Utilisées

- **Backend** : PHP 7.4+
- **Base de données** : MySQL
- **Frontend** : HTML5, CSS3, JavaScript (Vanilla)
- **PDF** : FPDF pour la génération de certificats
- **Email** : PHPMailer pour l'envoi d'emails
- **Serveur** : Apache (XAMPP)

## 📋 Prérequis

- PHP 7.4 ou supérieur
- MySQL 5.7 ou supérieur
- Apache Server
- XAMPP (recommandé pour le développement)

## 🚀 Installation

### 1. Cloner le projet
```bash
git clone [url-du-repo]
cd ClubConnect
```

### 2. Configuration de la base de données
1. Créez une base de données MySQL nommée `clubconnect`
2. Importez le fichier SQL fourni (si disponible)
3. Configurez les paramètres de connexion dans `configure.php`

### 3. Configuration des emails
Modifiez le fichier `email_config.php` avec vos paramètres SMTP :
```php
define('SMTP_HOST', 'votre-serveur-smtp');
define('SMTP_USERNAME', 'votre-email');
define('SMTP_PASSWORD', 'votre-mot-de-passe');
define('SMTP_PORT', 587);
define('SMTP_ENCRYPTION', 'tls');
```

### 4. Configuration XAMPP
1. Placez le projet dans le dossier `htdocs` de XAMPP
2. Démarrez Apache et MySQL
3. Accédez à `http://localhost/ClubConnect`

## 📁 Structure du Projet

```
ClubConnect/
├── admin/                  # Interface administrateur
│   └── admin.php          # Gestion des utilisateurs et clubs
├── organisateur/          # Interface organisateur
│   ├── certificats.php    # Gestion des certificats
│   ├── communication.php  # Système de messagerie
│   ├── createevent.php    # Création d'événements
│   ├── discoverevents.php # Découverte d'événements
│   ├── download_certificat.php # Téléchargement PDF
│   ├── home.php          # Tableau de bord
│   ├── MyClubs.php       # Mes clubs
│   └── MyEvents.php      # Mes événements
├── utilisateur/           # Interface utilisateur standard
│   ├── certificats.php    # Certificats utilisateur
│   ├── discoverevents.php # Découverte d'événements
│   ├── download_certificat.php # Téléchargement PDF
│   ├── home.php          # Accueil utilisateur
│   ├── MyClubs.php       # Mes clubs
│   └── MyEvents.php      # Mes événements
├── images/               # Images des événements
├── database.php          # Configuration base de données
├── configure.php         # Paramètres de configuration
├── email_config.php      # Configuration emails
├── signin.php           # Connexion
├── signup.php           # Inscription
└── index.php            # Page d'accueil
```

## 🗄️ Structure de la Base de Données

### Tables Principales
- **Utilisateur** : Informations des utilisateurs (nom, email, département, etc.)
- **Club** : Clubs étudiants
- **Evenement** : Événements organisés par les clubs
- **Inscription** : Inscriptions des utilisateurs aux événements
- **Attestation** : Certificats de participation générés automatiquement
- **Adherence** : Appartenance des utilisateurs aux clubs
- **Communication** : Messages entre utilisateurs

## 🔐 Système d'Authentification

- **Inscription** : Validation par email avec code de vérification
- **Connexion** : Authentification sécurisée par mot de passe hashé
- **Rôles** : Utilisateur, Organisateur, Administrateur
- **Sessions** : Gestion sécurisée des sessions utilisateur

## 📧 Système d'Emails

- **Vérification d'inscription** : Code de vérification envoyé par email
- **Notifications** : Notifications automatiques pour les événements
- **Communication** : Système de messagerie interne

## 📄 Génération de Certificats

Le système génère automatiquement des certificats PDF pour les participants aux événements terminés :
- **Génération automatique** : Certificats créés automatiquement après la fin des événements
- **Format PDF** : Certificats professionnels avec design personnalisé
- **Informations incluses** : Nom, événement, club organisateur, date
- **Téléchargement sécurisé** : Accès restreint aux certificats personnels

## 🎨 Interface Utilisateur

- **Design moderne** : Interface sombre avec effets visuels
- **Responsive** : Compatible mobile et desktop
- **Navigation intuitive** : Menu sidebar avec icônes
- **Animations** : Transitions fluides et effets hover

## 🔧 Fonctionnalités Techniques

### Sécurité
- **Protection SQL Injection** : Requêtes préparées
- **Validation des données** : Sanitisation des entrées utilisateur
- **Sessions sécurisées** : Gestion appropriée des sessions
- **Accès contrôlé** : Vérification des permissions utilisateur

### Performance
- **Requêtes optimisées** : Jointures efficaces et indexation
- **Cache des connexions** : Réutilisation des connexions base de données
- **Gestion des ressources** : Fermeture appropriée des connexions

## 🐛 Résolution de Problèmes

### Problèmes Courants

1. **Certificats non générés**
   - Vérifiez que les événements sont terminés (date passée)
   - Assurez-vous que l'utilisateur était inscrit à l'événement
   - Consultez les logs PHP pour les erreurs

2. **Erreurs de connexion base de données**
   - Vérifiez les paramètres dans `configure.php`
   - Assurez-vous que MySQL est démarré
   - Vérifiez les permissions de la base de données

3. **Emails non envoyés**
   - Vérifiez la configuration SMTP dans `email_config.php`
   - Testez avec un autre fournisseur email si nécessaire

## 📝 Utilisation

### Inscription
1. Accédez à la page d'inscription
2. Remplissez le formulaire avec vos informations étudiantes
3. Vérifiez votre email et entrez le code de vérification
4. Connectez-vous avec vos identifiants

### Création d'événement (Organisateurs)
1. Connectez-vous en tant qu'organisateur
2. Accédez à "Créer Événement"
3. Remplissez les détails (titre, description, date, lieu, capacité)
4. Ajoutez une photo si souhaité
5. Publiez l'événement

### Participation aux événements
1. Découvrez les événements disponibles
2. Cliquez sur "S'inscrire" pour les événements qui vous intéressent
3. Consultez "Mes Événements" pour suivre vos participations
4. Téléchargez vos certificats après la fin des événements

## 🤝 Contribution

1. Fork le projet
2. Créez une branche pour votre fonctionnalité (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Committez vos changements (`git commit -am 'Ajout d'une nouvelle fonctionnalité'`)
4. Push vers la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Ouvrez une Pull Request

## 📄 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 👥 Auteurs

- **Équipe de développement** - Développement initial
- **Contributeurs** - Améliorations et corrections

## 📞 Support

Pour toute question ou problème :
- Ouvrez une issue sur GitHub
- Contactez l'équipe de développement
- Consultez la documentation technique

## 🔮 Roadmap

### Fonctionnalités Futures
- [ ] Application mobile
- [ ] Notifications push
- [ ] Système de paiement pour événements payants
- [ ] Intégration avec calendriers externes
- [ ] Système de notation et avis
- [ ] API REST pour intégrations tierces

---

**ClubConnect** - Connecter les étudiants, un événement à la fois ! 🎓✨
