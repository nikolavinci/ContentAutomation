# Deployment Checklist

**Ship it.**

## 1. Pre-Launch (Week 1)
- [ ] API keys ready.
- [ ] WP App Passwords set.
- [ ] `.env` created and secure (`chmod 600`).
- [ ] `sites.yaml` configured.
- [ ] Dry run passed.
- [ ] Draft passed.
- [ ] Live publish passed.

## 2. Launch Day
- [ ] Deploy code to server.
- [ ] Set up Systemd service.
- [ ] Service running without errors.

## 3. Weekly Checks
- [ ] Logs clean? `tail -100 wp_scheduler.log`
- [ ] Articles indexed in Google?
- [ ] Traffic growing?
- [ ] WP and plugins updated?

```mermaid
graph LR
A[Pre-Launch] --> B[Launch Day]
B --> C[Weekly Ops]
C --> D[Monthly Review]
D -.->|Adjust Strategy| C
```

## Emergency Pause
STOP if:
- Success rate < 80%
- Plagiarism > 20%
- Google penalty
-> **Action**: Stop service, fix prompt/niche, test, resume.
