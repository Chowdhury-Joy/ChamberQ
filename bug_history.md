# Bug History

## 2026-07-28

<bug>
 <category>UI/UX</category>
 <symptom>Live Queue Control labeled a called patient as "Currently serving" while the badge correctly showed "Called — Waiting for Patient".</symptom>
 <root_cause>The section heading was hardcoded to "Currently serving" for every current booking, ignoring status (`called` vs `in_chamber`).</root_cause>
 <prevention_rule>Never label a patient as "Currently serving" unless their booking status is `in_chamber` (staff clicked Patient arrived).</prevention_rule>
</bug>
