import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';
import 'package:latlong2/latlong.dart';

import '../core/network/dio_client.dart';
import '../models/missing_case.dart';
import 'secure_storage_service.dart';

final _dio = DioClient(logBodies: false).dio;

class MissingCaseService {
  Future<({List<MissingCase> cases, bool hasMore})> list(
    MissingCaseKind? kind,
    int page,
  ) async {
    final response = await _dio.get<Map<String, dynamic>>(
      kind == null ? '/missing-cases/mine' : '/missing-cases',
      queryParameters: {if (kind != null) 'kind': kind.name, 'page': page},
    );
    final data = response.data!;
    final cases = (data['data'] as List)
        .cast<Map<String, dynamic>>()
        .map(MissingCase.fromJson)
        .toList();
    return (cases: cases, hasMore: data['links']['next'] != null);
  }

  Future<MissingCase> get(int id) async {
    final response = await _dio.get<Map<String, dynamic>>('/missing-cases/$id');
    return MissingCase.fromJson(response.data!);
  }

  Future<List<MissingCasePin>> map() async {
    final response = await _dio.get<List<dynamic>>('/missing-cases/map');
    return response.data!
        .cast<Map<String, dynamic>>()
        .map(MissingCasePin.fromJson)
        .toList();
  }

  Future<({List<MissingCaseContribution> items, bool hasMore})> contributions(
    int id,
    int page,
  ) async {
    final response = await _dio.get<Map<String, dynamic>>(
      '/missing-cases/$id/contributions',
      queryParameters: {'page': page},
    );
    final data = response.data!;
    final items = (data['data'] as List)
        .cast<Map<String, dynamic>>()
        .map(MissingCaseContribution.fromJson)
        .toList();
    return (items: items, hasMore: data['links']['next'] != null);
  }

  Future<void> contribute(
    int id,
    String message,
    List<XFile> photos, {
    XFile? video,
  }) async {
    final data = FormData.fromMap({if (message.isNotEmpty) 'message': message});
    await _addPhotos(data, photos);
    if (video != null) {
      data.files.add(
        MapEntry(
          'video',
          MultipartFile.fromStream(
            () => video.openRead(),
            await video.length(),
            filename: video.name,
          ),
        ),
      );
    }
    await _dio.post<void>('/missing-cases/$id/contributions', data: data);
  }

  Future<Map<String, String>> videoHeaders() async {
    final token = await SecureStorageService().getAccessToken();
    return {if (token != null) 'Authorization': 'Bearer $token'};
  }

  Uri videoUri(int id, int contribution) {
    final base = _dio.options.baseUrl.replaceFirst(RegExp(r'/+$'), '');
    return Uri.parse(
      '$base/missing-cases/$id/contributions/$contribution/video',
    );
  }

  Future<Uint8List> photo(int id, int index, {int? contributionId}) async {
    final path = contributionId == null
        ? '/missing-cases/$id'
        : '/missing-cases/$id/contributions/$contributionId';
    final response = await _dio.get<List<int>>(
      '$path/photos/$index',
      options: Options(responseType: ResponseType.bytes),
    );
    return Uint8List.fromList(response.data!);
  }

  Future<void> suggest({
    required MissingCaseKind kind,
    required String name,
    required String place,
    required String missingOn,
    required String description,
    required String phone,
    required List<XFile> photos,
    required LatLng location,
  }) async {
    final data = FormData.fromMap({
      'kind': kind.name,
      'name': name,
      'place': place,
      'missing_on': missingOn,
      'description': description,
      'reference_phone': phone,
      'latitude': location.latitude,
      'longitude': location.longitude,
    });
    await _addPhotos(data, photos);
    await _dio.post<void>('/missing-cases/suggestions', data: data);
  }

  Future<void> _addPhotos(FormData data, List<XFile> photos) async {
    for (final photo in photos) {
      data.files.add(
        MapEntry(
          'photos[]',
          MultipartFile.fromBytes(
            await photo.readAsBytes(),
            filename: photo.name,
          ),
        ),
      );
    }
  }
}
