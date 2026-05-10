# Missing PNG Files

This folder should contain the following PNG files that are referenced in the project but currently missing:

## Background Images (Referenced in styles.css)
- **backg2.png** - Main background image for dark mode
  - Referenced in `.root` class (line 3) and `.front-root` class (line 17)
  - Used as: `url("backg2.png")`

- **backgLIGHT2.png** - Background image for light mode  
  - Referenced in `.root.light-mode` class (line 10) and `.front-root.light-mode` class (line 23)
  - Used as: `url("backgLIGHT2.png")`

## UI Icons (Referenced in FXML files)
- **grid-view-svgrepo-com.png** - Grid view toggle icon
  - Referenced in `courses-layout.fxml` (line 50) and `library-layout.fxml` (line 52)
  - Used for grid view button in course and library layouts

- **list-view-svgrepo-com.png** - List view toggle icon
  - Referenced in `courses-layout.fxml` (line 59) and `library-layout.fxml` (line 59)  
  - Used for list view button in course and library layouts

## Usage Notes
- Background images should be sized appropriately for full-screen backgrounds
- Icon images should be sized around 18x18 pixels as specified in the FXML files
- All images should be in PNG format as referenced in the code

## Current Status
- ✅ backg2.png - Present (34,771 bytes)
- ✅ backgLIGHT2.png - Present (23,397 bytes)
- ✅ grid-view-svgrepo-com.png - Present (14,191 bytes)
- ✅ list-view-svgrepo-com.png - Present (21,057 bytes)

## Additional Assets Available
This directory also contains many other useful assets including:
- Various UI icons in both dark and light themes
- Additional background images (backg.png, backgLIGHT.png)
- Font files (Feather.ttf, SF-Pro-Text-Bold.otf, SF-Pro-Text-Light.otf)
- Application logo and other interface elements

All required PNG files are now present and the visual design of the application is complete.
