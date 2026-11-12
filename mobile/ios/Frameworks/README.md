# Plaud Official Embedded iOS SDK

This directory holds the official precompiled iOS framework bundles from:
https://github.com/Plaud-AI/plaud-sdk-public/tree/main/sdk/ios

### Required Frameworks:
- `PlaudBleSDK.xcframework`
- `PlaudWiFiSDK.xcframework`
- `PlaudDeviceBasicSDK.xcframework`

### Usage:
Add these frameworks under Xcode "Frameworks, Libraries, and Embedded Content" with "Embed & Sign".
For testing without physical devices or iOS hardware in CI, the app uses `MockPlaudSdkService`.
