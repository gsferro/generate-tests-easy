<?php

namespace Tests\Fixtures\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Tests\Fixtures\Models\Post;

class PostController extends Controller
{
    protected $model;
    public $isAPI = true;

    public function __construct(Post $model)
    {
        $this->model = $model;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $posts = $this->model->with('user')->paginate(10);
        return response()->json([
            'success' => true,
            'data' => $posts,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->model::$rules['store']);
        
        $post = $this->model->create($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Post created successfully',
            'data' => $post,
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $post = $this->model->with('user', 'comments')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $post,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $post = $this->model->findOrFail($id);
        
        // Replace {id} placeholder in validation rules
        $rules = $this->model::$rules['update'];
        $rules = array_map(function($rule) use ($id) {
            return str_replace('{id}', $id, $rule);
        }, $rules);
        
        $validated = $request->validate($rules);
        
        $post->update($validated);
        
        return response()->json([
            'success' => true,
            'message' => 'Post updated successfully',
            'data' => $post,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $post = $this->model->findOrFail($id);
        $post->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Post deleted successfully',
        ]);
    }
    
    /**
     * Get posts by a specific user.
     *
     * @param  int  $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function byUser($userId)
    {
        $posts = $this->model->byUser($userId)->paginate(10);
        
        return response()->json([
            'success' => true,
            'data' => $posts,
        ]);
    }
    
    /**
     * Get only published posts.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function published()
    {
        $posts = $this->model->published()->paginate(10);
        
        return response()->json([
            'success' => true,
            'data' => $posts,
        ]);
    }
}