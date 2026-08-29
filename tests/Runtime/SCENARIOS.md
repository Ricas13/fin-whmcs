# Runtime Scenarios

The real-service suite must eventually prove:

1. create -> remote user exists -> binding is durable
2. duplicate create -> no duplicate remote user
3. suspend -> playback/device/folder access disabled
4. unsuspend -> desired product policy restored
5. package change -> library/technical policy converges
6. terminate -> remote identity removed and local cleanup converges
7. ambiguous create -> unique operation-scoped temporary identity is observed safely
8. remote success/local write failure -> cron reconciliation repairs local state
9. concurrent lifecycle actions -> per-service lock serializes remote mutation
10. remote drift -> reconciliation restores desired state
