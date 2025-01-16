import 'package:flutter/material.dart';
import 'package:spacepad/components/spinner.dart';
import 'package:spacepad/services/auth_service.dart';
import 'package:spacepad/theme.dart';

class SplashPage extends StatefulWidget {
  const SplashPage({super.key});

  @override
  State<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends State<SplashPage> {
  @override
  void initState() {
    AuthService.instance.verify();

    super.initState();
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(
        child: Spinner(size: 40, thickness: 4, color: AppTheme.platinum),
      ),
    );
  }
}