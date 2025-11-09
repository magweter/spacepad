# Default Background Images

This directory contains the default background images that users can select for their displays.

## Required Images

Place the following 8 default background images in this directory:

1. `default_1.jpg` - First default background option
2. `default_2.jpg` - Second default background option
3. `default_3.jpg` - Third default background option
4. `default_4.jpg` - Fourth default background option
5. `default_5.jpg` - Fifth default background option
6. `default_6.jpg` - Sixth default background option
7. `default_7.jpg` - Seventh default background option
8. `default_8.jpg` - Eighth default background option

## Image Specifications

- **Format**: JPEG (JPG) recommended for file size optimization
- **Dimensions**: 1920x1080px or similar 16:9 aspect ratio
- **File Size**: Aim for under 500KB per image for optimal loading
- **Quality**: Use high-quality images that look good on large displays

## Design Recommendations

Good background images for room displays should:

- Have soft, muted colors that don't overpower text
- Avoid busy patterns that reduce readability
- Work well with white text overlay
- Be professional and appropriate for office environments
- Consider using:
  - Abstract gradients
  - Soft nature scenes (mountains, forests, water)
  - Minimalist geometric patterns
  - Blurred cityscapes
  - Subtle textures (wood, fabric, stone)

## Adding New Default Backgrounds

To add more default backgrounds:

1. Add the image file to this directory
2. Update `backend/app/Services/ImageService.php`:
   - Add the new background to the `DEFAULT_BACKGROUNDS` constant
3. Update `backend/app/Http/Requests/UpdateDisplayCustomizationRequest.php`:
   - Add the new key to the `default_background` validation rule

Example:
```php
public const DEFAULT_BACKGROUNDS = [
    'default_1' => 'images/backgrounds/default_1.jpg',
    'default_2' => 'images/backgrounds/default_2.jpg',
    'default_3' => 'images/backgrounds/default_3.jpg',
    'default_4' => 'images/backgrounds/default_4.jpg',
    'default_5' => 'images/backgrounds/default_5.jpg',
    'default_6' => 'images/backgrounds/default_6.jpg',
    'default_7' => 'images/backgrounds/default_7.jpg',
    'default_8' => 'images/backgrounds/default_8.jpg',
    'default_9' => 'images/backgrounds/default_9.jpg', // New background
];
```

