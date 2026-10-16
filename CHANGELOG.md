# Changelog

## 0.1.0

- First release, extracted from Maneevo's front-desk activation onboarding.
- `ActivationStep`, `Activatable` and `StepGate` contracts; `HasActivation` trait.
- `ActivationProgress`: progress, next step, activation stamping and regression, tour and hint state, celebration.
- `SubjectActivated` and `ActivationRegressed` events.
- Config-driven cache invalidation from watched models.
- Optional routes for tour progress, hint dismissal and celebration.
- Publishable migrations with configurable subject table, key type and column names.
