import 'dart:async';
import 'package:flutter/material.dart';
import '../services/api.dart';
import 'login_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});
  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final _api = ApiService();
  Timer? _timer;
  bool _loading = true;
  bool _refreshing = false;
  String? _error;
  int _tab = 0;
  Map<String, dynamic> _summary = {};
  List<Map<String, dynamic>> _servers = [];
  List<Map<String, dynamic>> _alerts = [];

  @override
  void initState() {
    super.initState();
    _load();
    _timer = Timer.periodic(const Duration(seconds: 30), (_) => _load(silent: true));
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _load({bool silent = false}) async {
    if (!silent && mounted) setState(() => _refreshing = true);
    try {
      final results = await Future.wait([
        _api.dashboard(),
        _api.servers(),
        _api.alerts(),
      ]);
      if (!mounted) return;
      setState(() {
        _summary = Map<String, dynamic>.from((results[0] as Map<String, dynamic>)['summary'] as Map? ?? {});
        _servers = results[1] as List<Map<String, dynamic>>;
        _alerts = results[2] as List<Map<String, dynamic>>;
        _error = null;
        _loading = false;
        _refreshing = false;
      });
    } catch (e) {
      if (e.toString().contains('SESSION_EXPIRED')) {
        await _api.logout();
        if (!mounted) return;
        Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute(builder: (_) => const LoginScreen()), (_) => false);
        return;
      }
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
        _refreshing = false;
      });
    }
  }

  Future<void> _logout() async {
    await _api.logout();
    if (!mounted) return;
    Navigator.of(context).pushAndRemoveUntil(MaterialPageRoute(builder: (_) => const LoginScreen()), (_) => false);
  }

  Color _statusColor(Map<String, dynamic> s) {
    if (s['limit_reached'] == true) return Colors.red;
    if (s['last_status'] == 'online') return Colors.green;
    if (s['last_status'] == 'offline') return Colors.red;
    return Colors.orange;
  }

  String _value(dynamic value, {String suffix = ''}) {
    if (value == null || value.toString().isEmpty) return '-';
    return '${value.toString()}$suffix';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_tab == 0 ? 'لوحة المراقبة' : 'التنبيهات'),
        actions: [
          IconButton(onPressed: _refreshing ? null : _load, icon: const Icon(Icons.refresh)),
          PopupMenuButton<String>(
            onSelected: (v) { if (v == 'logout') _logout(); },
            itemBuilder: (_) => const [PopupMenuItem(value: 'logout', child: Text('تسجيل الخروج'))],
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _errorView()
              : RefreshIndicator(onRefresh: _load, child: _tab == 0 ? _dashboardView() : _alertsView()),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _tab,
        onDestinationSelected: (i) => setState(() => _tab = i),
        destinations: [
          const NavigationDestination(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard), label: 'السيرفرات'),
          NavigationDestination(icon: const Icon(Icons.notifications_none), selectedIcon: const Icon(Icons.notifications), label: 'التنبيهات ${_alerts.isEmpty ? '' : '(${_alerts.length})'}'),
        ],
      ),
    );
  }

  Widget _errorView() => RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(24),
          children: [
            const SizedBox(height: 70),
            const Icon(Icons.error_outline, size: 58, color: Colors.red),
            const SizedBox(height: 14),
            Center(child: Text(_error!, textAlign: TextAlign.center)),
            const SizedBox(height: 18),
            Center(child: FilledButton(onPressed: _load, child: const Text('إعادة المحاولة'))),
          ],
        ),
      );

  Widget _dashboardView() {
    return ListView(
      padding: const EdgeInsets.all(12),
      children: [
        GridView.count(
          crossAxisCount: MediaQuery.of(context).size.width > 700 ? 4 : 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          crossAxisSpacing: 10,
          mainAxisSpacing: 10,
          childAspectRatio: 1.7,
          children: [
            _summaryCard('السيرفرات', _summary['servers'], Icons.dns),
            _summaryCard('Online', _summary['online'], Icons.check_circle, color: Colors.green),
            _summaryCard('Offline', _summary['offline'], Icons.error, color: Colors.red),
            _summaryCard('المواقع', _summary['websites'], Icons.public),
          ],
        ),
        const SizedBox(height: 14),
        ..._servers.map(_serverCard),
      ],
    );
  }

  Widget _summaryCard(String title, dynamic value, IconData icon, {Color? color}) {
    final c = color ?? const Color(0xFF334155);
    return Card(
      color: Colors.white,
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            CircleAvatar(backgroundColor: c.withOpacity(.10), foregroundColor: c, child: Icon(icon)),
            const SizedBox(width: 10),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [Text(title, style: const TextStyle(color: Colors.black54)), Text('${value ?? 0}', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold))])),
          ],
        ),
      ),
    );
  }

  Widget _serverCard(Map<String, dynamic> s) {
    final statusColor = _statusColor(s);
    final limit = (s['website_hard_limit'] as num?)?.toInt() ?? 0;
    final sites = (s['accounts'] as num?)?.toInt() ?? 0;
    final services = [
      ['Apache', s['apache_status']],
      ['MySQL', s['mysql_status']],
      ['DNS', s['dns_status']],
      ['Exim', s['exim_status']],
    ];

    return Card(
      color: s['limit_reached'] == true ? Colors.red.shade50 : Colors.white,
      margin: const EdgeInsets.only(bottom: 10),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          children: [
            Row(
              children: [
                CircleAvatar(backgroundColor: statusColor, foregroundColor: Colors.white, child: const Icon(Icons.dns_outlined)),
                const SizedBox(width: 10),
                Expanded(child: Text('${s['sort_order']}. ${s['name']}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold))),
                Text((s['last_status'] ?? 'unknown').toString().toUpperCase(), style: TextStyle(color: statusColor, fontWeight: FontWeight.bold)),
              ],
            ),
            const Divider(height: 22),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _metricChip('المواقع', limit > 0 ? '$sites / $limit' : '$sites'),
                _metricChip('Active', _value(s['active_accounts'])),
                _metricChip('Suspended', _value(s['suspended_accounts'])),
                _metricChip('Load', _value(s['load_1m'])),
                _metricChip('RAM', _value(s['ram_percent'], suffix: '%')),
                _metricChip('Disk', _value(s['disk_percent'], suffix: '%')),
                _metricChip('Backup', _value(s['backup_percent'], suffix: '%')),
              ],
            ),
            const SizedBox(height: 12),
            Align(
              alignment: Alignment.centerRight,
              child: Wrap(
                spacing: 6,
                runSpacing: 6,
                children: services.map((e) {
                  final ok = ['active', 'enabled'].contains((e[1] ?? '').toString().toLowerCase());
                  return Chip(
                    avatar: Icon(ok ? Icons.check : Icons.close, size: 16, color: ok ? Colors.green : Colors.red),
                    label: Text('${e[0]}: ${e[1] ?? '-'}'),
                  );
                }).toList(),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _metricChip(String label, String value) => Chip(label: Text('$label: $value'));

  Widget _alertsView() {
    if (_alerts.isEmpty) {
      return ListView(children: const [SizedBox(height: 100), Center(child: Icon(Icons.notifications_off_outlined, size: 54, color: Colors.grey)), SizedBox(height: 12), Center(child: Text('لا توجد تنبيهات نشطة'))]);
    }
    return ListView.separated(
      padding: const EdgeInsets.all(12),
      itemCount: _alerts.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (_, i) {
        final a = _alerts[i];
        return Card(
          color: Colors.white,
          child: ListTile(
            leading: CircleAvatar(backgroundColor: Colors.red.shade50, foregroundColor: Colors.red, child: const Icon(Icons.warning_amber_rounded)),
            title: Text((a['server_name'] ?? 'Server').toString(), style: const TextStyle(fontWeight: FontWeight.bold)),
            subtitle: Text('${a['alert_type'] ?? '-'}\n${a['last_sent_at'] ?? '-'}'),
            isThreeLine: true,
          ),
        );
      },
    );
  }
}
