import 'dart:async';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:spacepad/services/auth_service.dart';
import 'package:get/get.dart';

class AuthenticatedImage extends StatefulWidget {
  final String imageUrl;
  final double? width;
  final double? height;
  final BoxFit fit;
  final Widget? placeholder;
  final Widget? errorWidget;

  const AuthenticatedImage({
    Key? key,
    required this.imageUrl,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.placeholder,
    this.errorWidget,
  }) : super(key: key);

  @override
  State<AuthenticatedImage> createState() => _AuthenticatedImageState();
}

class _AuthenticatedImageState extends State<AuthenticatedImage> {
  ImageProvider? _imageProvider;
  bool _isLoading = true;
  bool _hasError = false;

  @override
  void initState() {
    super.initState();
    _loadImage();
  }

  @override
  void didUpdateWidget(AuthenticatedImage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.imageUrl != widget.imageUrl) {
      _loadImage();
    }
  }

  Future<void> _loadImage() async {
    if (!mounted) return;

    setState(() {
      _isLoading = true;
      _hasError = false;
    });

    try {
      // Get authentication headers
      final headers = <String, String>{
        'Accept': 'application/json',
        'Accept-Language': Get.locale?.languageCode ?? 'en',
      };

      if (AuthService.instance.getAuthToken() != null) {
        headers['Authorization'] = 'Bearer ${AuthService.instance.getAuthToken()}';
      }

      // Make authenticated request with timeout
      final response = await http.get(
        Uri.parse(widget.imageUrl),
        headers: headers,
      ).timeout(
        const Duration(seconds: 10),
        onTimeout: () {
          throw TimeoutException('Image load timeout after 10 seconds', const Duration(seconds: 10));
        },
      );

      if (response.statusCode == 200) {
        // Create image provider from bytes
        _imageProvider = MemoryImage(response.bodyBytes);
        
        if (mounted) {
          setState(() {
            _isLoading = false;
            _hasError = false;
          });
        }
      } else {
        throw Exception('Failed to load image: ${response.statusCode}');
      }
    } on TimeoutException {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _hasError = true;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _hasError = true;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return widget.placeholder ?? 
        Container(
          width: widget.width,
          height: widget.height,
          child: Center(
            child: CircularProgressIndicator(
              strokeWidth: 2,
              valueColor: AlwaysStoppedAnimation<Color>(Colors.grey),
            ),
          ),
        );
    }

    if (_hasError || _imageProvider == null) {
      return widget.errorWidget ?? SizedBox.shrink();
    }

    return Image(
      image: _imageProvider!,
      width: widget.width,
      height: widget.height,
      fit: widget.fit,
    );
  }
}
