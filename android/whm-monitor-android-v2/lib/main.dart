import 'package:flutter/material.dart';
import 'screens/login_screen.dart';
import 'screens/dashboard_screen.dart';
import 'services/api.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final api = ApiService();
  final loggedIn = await api.hasToken();
  runApp(WHMMonitorApp(initialLoggedIn: loggedIn));
}

class WHMMonitorApp extends StatelessWidget {
  final bool initialLoggedIn;
  const WHMMonitorApp({super.key, required this.initialLoggedIn});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'WHM Server Monitor',
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF1E293B)),
        scaffoldBackgroundColor: const Color(0xFFF3F5F8),
        cardTheme: const CardThemeData(
          elevation: 0,
          margin: EdgeInsets.zero,
        ),
      ),
      home: initialLoggedIn ? const DashboardScreen() : const LoginScreen(),
    );
  }
}
