import 'dart:async';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:spacepad/services/auth_service.dart';
import 'package:get/get.dart';

class AuthenticatedBackground extends StatefulWidget {
  final String? imageUrl;
  final Widget child;
  final BorderRadius? borderRadius;

  const AuthenticatedBackground({
    Key? key,
    this.imageUrl,
    required this.child,
    this.borderRadius,
  }) : super(key: key);

  @override
  State<AuthenticatedBackground> createState() => _AuthenticatedBackgroundState();
}

class _AuthenticatedBackgroundState extends State<AuthenticatedBackground> {
  ImageProvider? _imageProvider;
  bool _hasError = false;

  @override
  void initState() {
    super.initState();
    if (widget.imageUrl != null) {
      _loadImage();
    }
  }

  @override
  void didUpdateWidget(AuthenticatedBackground oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.imageUrl != widget.imageUrl) {
      if (widget.imageUrl != null) {
        _loadImage();
      } else {
        setState(() {
          _hasError = false;
          _imageProvider = null;
        });
      }
    }
  }

  Future<void> _loadImage() async {
    if (!mounted || widget.imageUrl == null) return;

    setState(() {
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
        Uri.parse(widget.imageUrl!),
        headers: headers,
      ).timeout(
        const Duration(seconds: 15),
        onTimeout: () {
          throw TimeoutException('Image load timeout after 15 seconds');
        },
      );

      if (response.statusCode == 200) {
        // Create image provider from bytes
        _imageProvider = MemoryImage(response.bodyBytes);
        
        if (mounted) {
          setState(() {
            _hasError = false;
          });
        }
      } else {
        throw Exception('Failed to load image: ${response.statusCode}');
      }
    } on TimeoutException {
      if (mounted) {
        setState(() {
          _hasError = true;
        });
      }
      return;
    } catch (e) {
      if (mounted) {
        setState(() {
          _hasError = true;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        borderRadius: widget.borderRadius,
        color: Colors.black,
        image: _imageProvider != null && !_hasError
            ? DecorationImage(
                image: _imageProvider!,
                fit: BoxFit.cover,
                colorFilter: ColorFilter.mode(
                  Colors.black.withValues(alpha: 0.3),
                  BlendMode.srcOver,
                ),
              )
            : null,
      ),
      child: widget.child,
    );
  }
}
