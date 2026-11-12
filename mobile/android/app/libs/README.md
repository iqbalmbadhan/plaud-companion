# Plaud Official Embedded Android SDK

This directory holds the official precompiled Android binary (`plaud-sdk.aar`) from:
https://github.com/Plaud-AI/plaud-sdk-public/tree/main/sdk/android

### Instructions
1. Download or copy `plaud-sdk.aar` into this directory:
   `mobile/android/app/libs/plaud-sdk.aar`
2. `mobile/android/app/build.gradle` automatically includes all `.aar` and `.jar` binaries found in `libs`.
3. In mock / simulator mode, the Flutter app operates via `MockPlaudSdkService` so hardware is not strictly required during local widget development and CI workflows.
