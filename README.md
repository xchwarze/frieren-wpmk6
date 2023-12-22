# Frieren example

## Description

As an example I made this implementation of Frieren as a backend of wifi-pineapple mk6. By the way this repo fixes some bugs like the one that occurs when not configuring CORS.

## About the refactor

I used the Rector refactor engine and wrote a few rules that pretty much automate the porting of code from wifi-pineapple mk6 to this version using Frieren.

To make the refactor I did this:

- **Rector**: I copy the entire module or panel that I want to refactor with Rector to tools/rector/pineapple. After that I simply open a console in tools/rector and run `vendor/bin/rector process`.
- **Manual steps**: The manual steps should be no more than the following
  ```
  __construct() -> Now the call to the route is made by the constructor so whenever you need to do parent::__construct($request); do it at the end.
  
  route() -> Check that the original function did not do any extra things in its code
  
  DatabaseConnection() -> Use the new orm class \frieren\orm\SQLite()
  
  downloadFile() -> Use new method name generateDownloadFile()
  
  /helper/* -> Check if any method is still trying to run from the old /helper/ namespace.
  ```

## Notes

The base repo used was my version which has many fixes and improvements: https://github.com/xchwarze/wifi-pineapple-panel

To install this version simply copy the contents of the src folder to the root of the device.
