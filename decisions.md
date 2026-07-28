# Decisions Log

## 2026-07-28

<decision>
 <category>UI/UX</category>
 <context>Live Queue Control showed the heading "Currently serving" for any current booking, including patients who were only called and still waiting to arrive. Staff found this confusing because serving has not started yet.</context>
 <action>Make the heading status-aware: "Currently calling" when status is `called`, "Currently serving" only when status is `in_chamber` (after Patient arrived), and "No active call" when there is no current booking.</action>
 <reason>Matches the real workflow — a patient is only being served after staff confirm they arrived. The badge already said "Called — Waiting for Patient"; the heading now agrees with that state.</reason>
</decision>
