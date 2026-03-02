# Plan d'implémentation (rôles, 2FA, emails)

## Objectif
Mettre en place 2 rôles (`ROLE_SUPER_ADMIN` et `ROLE_PRO`), un parcours d’inscription pro avec fiche entreprise, une connexion 2FA, et des emails transactionnels (validation, changement de mot de passe, confirmation de connexion).

## Hypothèses
- Projet Symfony + Doctrine + Symfony Mailer déjà en place.
- L’auth est Symfony Security + Login form.
- Le mailer sera configuré côté infra par vous.

## Rôles et accès
1. `ROLE_SUPER_ADMIN`
   - Accès complet.
   - Gestion des comptes pros et de leurs fiches.
2. `ROLE_PRO`
   - Accès uniquement à la page de son entreprise.
   - Pas d’accès admin.

## Modèle de données (Company séparée)
1. `User`
   - `email`, `password`, `roles`, `isVerified`
   - `twoFactorEnabled`
   - `lastLoginAt`
   - `pendingEmailChangeToken` (si besoin)
2. `Company` (fiche pro, entité séparée de User)
   - `name`, `siret`, `address`, `phone`, `website`, `description`
   - `owner` (relation `ManyToOne` vers `User`)
3. `SecurityToken`
   - `type` (CONFIRM_EMAIL, RESET_PASSWORD, CONFIRM_LOGIN)
   - `token`, `expiresAt`, `usedAt`
   - `user`

## Parcours PRO
1. Inscription PRO
   - Formulaire: infos compte + fiche entreprise.
   - Création utilisateur `ROLE_PRO` + fiche `Company`.
   - Génération token `CONFIRM_EMAIL`.
   - Envoi email de confirmation.
2. Confirmation email
   - Lien reçu → page “définir mot de passe”.
   - À la validation:
     - Mot de passe défini.
     - `isVerified = true`.
     - `twoFactorEnabled = false` (temporaire).
3. Première connexion
   - Connexion autorisée avec mot de passe.
   - Après la 1ère connexion réussie:
     - Activer `twoFactorEnabled = true`.

## Connexion et 2FA (email simple)
1. Si `twoFactorEnabled = true`
   - Envoi d’un code 2FA par email (code à 6 chiffres).
   - Validation du code obligatoire.
2. Si `twoFactorEnabled = false`
   - Connexion directe.

## Changement de mot de passe
1. Si l’utilisateur est connecté:
   - Demande de changement de mot de passe.
   - Envoi email de confirmation.
   - Changement effectif uniquement après confirmation.
2. Si “mot de passe oublié”
   - Génération token RESET_PASSWORD.
   - Email avec lien de reset.

## Emails (Symfony Mailer, FR)
1. Confirmation création de compte pro
2. Confirmation de connexion (si nouvelle connexion ou appareil)
3. Confirmation de changement de mot de passe
4. Reset mot de passe (mot de passe oublié)

## Sécurité
1. Expiration des tokens (ex: 5min pour login, 1h pour reset).
2. Un token usage unique.
3. Journalisation des connexions.

## Étapes d’implémentation
1. Entités + migrations (`User`, `Company`, `SecurityToken`).
2. Security: rôles + accès par route.
3. Formulaire d’inscription pro + persistance.
4. Service d’email + templates.
5. Workflow de confirmation email + création mot de passe.
6. 2FA (email ou TOTP).
7. Changement mot de passe avec confirmation email.
8. Tests fonctionnels basiques (login, 2FA, reset).
